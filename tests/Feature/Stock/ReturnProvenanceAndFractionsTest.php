<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\QuarantineStatus;
use App\Enums\ReturnReason;
use App\Enums\StockMovementType;
use App\Models\CostAllocation;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Review follow-up (stage 11): a return of an item from mixed-expiry or unknown batches never becomes sellable
 * as if fresh; an expired restock settles no provisional sale; fractional supplier returns and intakes are
 * accepted at the line's unit precision.
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

    $this->sold = function (int $quantity): SaleItem {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'customer_id' => null]);

        return SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'variation_id' => $this->variation->id,
            'quantity' => $quantity, 'base_quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity,
            'unit_cost' => 5, 'total_cost' => 5 * $quantity, 'status' => 'completed',
        ]);
    };
    $this->returnAll = function (SaleItem $line, int $quantity): void {
        $return = app(ReturnService::class)->createReturn([
            'sale_id' => $line->sale_id, 'reason' => ReturnReason::WRONG_ITEM->value, 'items' => [['sale_item_id' => $line->id, 'quantity' => $quantity]],
        ], $this->owner->id);
        app(ReturnService::class)->approveReturn($return, $this->owner->id);
        app(ReturnService::class)->receiveReturn($return);
    };
});

/**
 * A completed intake of an accepted quantity of a product, against a received purchase order line.
 */
function completedIntake(Product $product, int $accepted): StockIntake
{
    $supplier = Supplier::factory()->create();
    $shop = Shop::factory()->create();
    $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'shop_id' => $shop->id, 'status' => PurchaseOrderStatus::RECEIVED]);
    $item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
        'quantity_ordered' => 10, 'quantity_received' => 10, 'quantity_remaining' => 0, 'unit_cost' => 5,
    ]);

    return StockIntake::factory()->completed()->create([
        'purchase_order_id' => $purchaseOrder->id, 'purchase_order_item_id' => $item->id, 'supplier_id' => $supplier->id,
        'shop_id' => $shop->id, 'product_id' => $product->id, 'quantity_received' => 10, 'quantity_accepted' => $accepted,
        'quantity_rejected' => 0, 'unit' => 'pcs',
    ]);
}

function batchLayer(string $batch, string $expiry, string $receivedAt): array
{
    return ['batch_number' => $batch, 'expiry_date' => $expiry, 'received_at' => $receivedAt];
}

it('returns one item from a sale spread over an expired and an unexpired batch as an expired item', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 1, 5.0, StockMovementType::INTAKE, batchLayer('OLD', '2027-06-01', '2027-01-01'));
    $this->ledger->receive($this->shop->id, $this->variation, 1, 5.0, StockMovementType::INTAKE, batchLayer('NEW', '2028-01-01', '2027-02-01'));
    $oldLayer = CostLayer::query()->where('batch_number', 'OLD')->firstOrFail();
    $newLayer = CostLayer::query()->where('batch_number', 'NEW')->firstOrFail();

    // Two whole items, one from each batch.
    $line = ($this->sold)(2);
    $this->ledger->consume($this->shop->id, $this->variation, 1, StockMovementType::SALE, $line, [], $oldLayer->id);
    $this->ledger->consume($this->shop->id, $this->variation, 1, StockMovementType::SALE, $line, [], $newLayer->id);

    // After the OLD batch expires, the customer returns one item. Which one is not recorded, so the return is
    // restocked as a single unit at the earlier expiry: it is never offered as fresh stock.
    $this->travelTo(Carbon::parse('2027-07-01 10:00:00', 'Africa/Nairobi'));
    ($this->returnAll)($line, 1);

    $restocked = CostLayer::query()->where('source_type', CostLayer::SOURCE_SALE_RETURN)->get();
    expect($restocked)->toHaveCount(1)
        ->and((string) $restocked->first()->original_quantity)->toBe('1.000')
        ->and($restocked->first()->expiry_date->toDateString())->toBe('2027-06-01')
        // The returned item is expired, so it is stock on hand but not sellable.
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('1.000')
        ->and($this->ledger->sellable($this->shop->id, $this->variation->id))->toBe(0);
});

it('quarantines a return whose sale has no recorded batch: it goes back already expired', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 5, 5.0, StockMovementType::INTAKE, batchLayer('HEALTHY', '2028-01-01', '2027-01-01'));

    // A sale line with no cost allocations at all (a sale recorded before batches): its batch cannot be known.
    $line = ($this->sold)(1);
    ($this->returnAll)($line, 1);

    // Quarantined as unverified (its batch cannot be established), with no expiry invented for it.
    $quarantine = CostLayer::query()->where('source_type', CostLayer::SOURCE_SALE_RETURN)->firstOrFail();
    expect($quarantine->quarantine_status)->toBe(QuarantineStatus::UNVERIFIED)
        ->and($quarantine->expiry_date)->toBeNull()
        ->and($this->ledger->exactQuantity($this->shop->id, $this->variation->id))->toBe('6.000')
        ->and($this->ledger->sellable($this->shop->id, $this->variation->id))->toBe(5);
});

it('does not settle a provisional sale with an expired restock, but does with a sellable one', function () {
    // A sale taken before any stock arrived: its allocation is provisional, waiting for stock.
    $line = ($this->sold)(3);
    $this->ledger->consume($this->shop->id, $this->variation, 3, StockMovementType::SALE, $line);
    expect(CostAllocation::query()->where('is_provisional', true)->count())->toBe(1);

    // Expired goods come back into stock: they are not sold, so the sale stays provisional.
    $this->ledger->receive($this->shop->id, $this->variation, 2, 5.0, StockMovementType::RETURN, batchLayer('EXPIRED', '2027-01-01', '2026-12-01'));
    expect(CostAllocation::query()->where('is_provisional', true)->count())->toBe(1)
        ->and(CostLayer::query()->where('batch_number', 'EXPIRED')->value('remaining_quantity'))->toBe('2.000');

    // Sellable stock arriving settles it, as before.
    $this->ledger->receive($this->shop->id, $this->variation, 5, 5.0, StockMovementType::INTAKE, batchLayer('FRESH', '2028-01-01', '2027-03-01'));
    expect(CostAllocation::query()->where('is_provisional', true)->count())->toBe(0);
});

it('takes a fractional supplier return of a weight item, and refuses one of a whole-unit item', function () {
    $weight = Product::factory()->create(['stock_quantity' => 20, 'unit' => 'kg']);
    $weight->defaultVariation->baseUnit()->update(['precision' => 3]);
    $whole = Product::factory()->create(['stock_quantity' => 20, 'unit' => 'piece']);
    $whole->defaultVariation->baseUnit()->update(['precision' => 0]);

    $weightIntake = completedIntake($weight, 10);
    $wholeIntake = completedIntake($whole, 10);

    $store = fn ($intake, string $quantity) => $this->actingAs($this->owner)->post(route('purchase-returns.store'), [
        'status' => 'pending', 'reason' => 'damaged',
        'items' => [['stock_intake_id' => $intake->id, 'purchase_order_item_id' => $intake->purchase_order_item_id, 'product_id' => $intake->product_id, 'quantity' => $quantity, 'condition' => 'damaged']],
    ]);

    $store($weightIntake, '2.5')->assertSessionHasNoErrors();
    expect(DB::table('purchase_return_items')->where('stock_intake_id', $weightIntake->id)->value('quantity'))->toEqual(2.5);

    $store($wholeIntake, '2.5')->assertSessionHasErrors('items.0.quantity');
});

it('receives the smallest quantity its unit allows, and refuses a fraction of a whole-unit product', function () {
    $weight = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'unit' => 'kg']);
    $weight->defaultVariation->baseUnit()->update(['precision' => 3]);
    $whole = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'unit' => 'piece']);
    $whole->defaultVariation->baseUnit()->update(['precision' => 0]);

    $intake = fn (Product $product, string $received) => $this->actingAs($this->owner)->post(route('stock-intakes.store'), [
        'product_id' => $product->id, 'shop_id' => $this->shop->id, 'intake_date' => now()->toDateString(),
        'quantity_received' => $received, 'quantity_accepted' => $received, 'quantity_rejected' => 0,
    ]);

    $intake($weight, '0.001')->assertSessionHasNoErrors();
    $intake($whole, '0.5')->assertSessionHasErrors('quantity_received');
});

it('returns an ordinary product sold from one unbatched layer as sellable: no batch or expiry applies to it', function () {
    // An ordinary product: batches are not tracked, so there is no batch or expiry to establish. Its one unbatched layer is
    // the provenance, and it is established. (A medical product, or one that tracks batches, is quarantined instead.)
    $ordinary = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece']);
    $ordinary->defaultVariation->update(['track_batches' => false]);
    $variation = $ordinary->defaultVariation;
    $this->ledger->receive($this->shop->id, $variation, 5, 5.0, StockMovementType::INTAKE);

    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => 'completed', 'customer_id' => null]);
    $line = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $ordinary->id, 'variation_id' => $variation->id,
        'quantity' => 1, 'base_quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'unit_cost' => 5, 'total_cost' => 5, 'status' => 'completed',
    ]);
    $this->ledger->consume($this->shop->id, $variation, 1, StockMovementType::SALE, $line);

    $return = app(ReturnService::class)->createReturn([
        'sale_id' => $sale->id, 'reason' => ReturnReason::WRONG_ITEM->value, 'items' => [['sale_item_id' => $line->id, 'quantity' => 1]],
    ], $this->owner->id);
    app(ReturnService::class)->approveReturn($return, $this->owner->id);
    app(ReturnService::class)->receiveReturn($return);

    $restocked = CostLayer::query()->where('source_type', CostLayer::SOURCE_SALE_RETURN)->firstOrFail();
    expect($restocked->quarantine_status)->toBeNull()
        ->and($this->ledger->sellableQuantity($this->shop->id, $variation->id))->toBe('5.000');
});
