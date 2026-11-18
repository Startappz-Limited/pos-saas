<?php

use App\Enums\AdjustmentType;
use App\Enums\ReturnReason;
use App\Enums\StockMovementType;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use App\Support\Stock\AdjustmentApproval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * Review follow-up (stage 11): a return restocks into the batch it was sold from, with that batch's expiry,
 * so expired stock is not sellable again; a decrease is valued at the batch it takes, not the cheapest layer.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2027-05-01 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->ledger = app(StockLedger::class);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece']);
    $this->variation = $this->product->defaultVariation;
    $this->variation->update(['track_batches' => true]);
});

it('restocks an expired return into its original batch and expiry, so it is not sellable again', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 10, 5.0, StockMovementType::INTAKE, [
        'batch_number' => 'B1', 'expiry_date' => '2027-06-01', 'received_at' => '2027-01-01',
    ]);

    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'customer_id' => null]);
    $saleItem = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'variation_id' => $this->variation->id,
        'quantity' => 2, 'base_quantity' => 2, 'unit_price' => 100, 'line_total' => 200, 'unit_cost' => 5, 'total_cost' => 10, 'status' => 'completed',
    ]);
    $this->ledger->consume($this->shop->id, $this->variation, 2, StockMovementType::SALE, $saleItem);

    // The batch expires after the sale, and the customer brings the goods back after that.
    $this->travelTo(Carbon::parse('2027-07-01 10:00:00', 'Africa/Nairobi'));

    $return = app(ReturnService::class)->createReturn([
        'sale_id' => $sale->id, 'reason' => ReturnReason::WRONG_ITEM->value, 'items' => [['sale_item_id' => $saleItem->id, 'quantity' => 2]],
    ], $this->owner->id);
    app(ReturnService::class)->approveReturn($return, $this->owner->id);
    app(ReturnService::class)->receiveReturn($return);

    $restocked = CostLayer::query()->where('source_type', CostLayer::SOURCE_SALE_RETURN)->firstOrFail();
    expect($restocked->batch_number)->toBe('B1')
        ->and($restocked->expiry_date->toDateString())->toBe('2027-06-01')
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('10.000')
        // Every batch here has expired by now, the original one included: none of it is sellable.
        ->and($this->ledger->sellable($this->shop->id, $this->variation->id))->toBe(0);
});

it('values a decrease at the batch it takes, not the cheapest layer', function () {
    // An old cheap layer and a newer expensive batch, the one the write-off names.
    $this->ledger->receive($this->shop->id, $this->variation, 10, 2.0, StockMovementType::INTAKE, ['received_at' => '2027-01-01']);
    $this->ledger->receive($this->shop->id, $this->variation, 10, 50.0, StockMovementType::INTAKE, ['batch_number' => 'DEAR', 'received_at' => '2027-03-01']);
    $dear = CostLayer::query()->where('batch_number', 'DEAR')->firstOrFail();

    // Valued at the named batch: 10 x 50, not the FIFO 10 x 2.
    expect($this->ledger->costToConsume($this->shop->id, $this->variation, 10, $dear->id, true))->toBe(50.0)
        ->and($this->ledger->costToConsume($this->shop->id, $this->variation, 10))->toBe(2.0);
});

it('values an approval decrease at its named batch cost, so the threshold decision is right', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 10, 2.0, StockMovementType::INTAKE, ['received_at' => '2027-01-01']);
    $this->ledger->receive($this->shop->id, $this->variation, 10, 50.0, StockMovementType::INTAKE, ['batch_number' => 'DEAR', 'received_at' => '2027-03-01']);
    $dear = CostLayer::query()->where('batch_number', 'DEAR')->firstOrFail();

    $adjustment = StockAdjustment::factory()->create(['shop_id' => $this->shop->id, 'status' => 'pending', 'type' => AdjustmentType::DECREASE, 'reason' => 'damaged', 'created_by' => $this->owner->id]);
    $item = StockAdjustmentItem::create([
        'stock_adjustment_id' => $adjustment->id, 'product_id' => $this->product->id, 'variation_id' => $this->variation->id,
        'shop_id' => $this->shop->id, 'quantity_change' => -10, 'cost_layer_id' => $dear->id,
    ]);

    expect(app(AdjustmentApproval::class)->value($adjustment->fresh()->load('items')))->toBe(500.0);
});
