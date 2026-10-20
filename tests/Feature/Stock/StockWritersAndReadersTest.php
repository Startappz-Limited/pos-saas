<?php

use App\Actions\CheckLowStockLevels;
use App\Actions\CreateInventorySnapshot;
use App\Actions\PurchaseReturn\MarkPurchaseReturnShippedAction;
use App\Actions\StockIntake\CompleteStockIntakeAction;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockIntake;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\Stock\StockLedger;
use App\Services\StockAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Every stock writer and reader works on per-shop stock with FIFO layers
 * (stage 1b).
 */
beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'cost_price' => 15, 'reorder_level' => 5]);
    $this->variation = $this->product->defaultVariation;
});

function layers(): array
{
    return DB::table('cost_layers')->where('product_id', test()->product->id)->orderBy('id')->get(['source_type', 'unit_cost', 'remaining_quantity'])
        ->map(fn ($layer) => [$layer->source_type, $layer->unit_cost === null ? null : (float) $layer->unit_cost, (int) $layer->remaining_quantity])->all();
}

describe('writers', function () {
    it('turns a completed intake into the shop\'s stock, layered at the purchase order\'s cost', function () {
        $order = PurchaseOrder::factory()->ordered()->create(['shop_id' => $this->shop->id]);
        $line = PurchaseOrderItem::factory()->create(['purchase_order_id' => $order->id, 'product_id' => $this->product->id, 'unit_cost' => 12.5]);
        $intake = StockIntake::factory()->pending()->create([
            'shop_id' => $this->shop->id, 'product_id' => $this->product->id, 'purchase_order_id' => $order->id,
            'purchase_order_item_id' => $line->id, 'quantity_accepted' => 8,
        ]);

        app(CompleteStockIntakeAction::class)->execute($intake, $this->user->id);

        expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(8)
            ->and(layers())->toBe([['intake', 12.5, 8]])
            ->and((int) DB::table('cost_layers')->where('product_id', $this->product->id)->value('stock_intake_id'))->toBe($intake->id);
    });

    it('puts a variation\'s intake on that variation, not the product', function () {
        $variation = ProductVariation::factory()->create(['product_id' => $this->product->id, 'stock_quantity' => 0, 'cost_price' => 9]);
        $intake = StockIntake::factory()->pending()->create([
            'shop_id' => $this->shop->id, 'product_id' => $this->product->id, 'product_variation_id' => $variation->id,
            'purchase_order_id' => null, 'purchase_order_item_id' => null, 'quantity_accepted' => 4,
        ]);

        app(CompleteStockIntakeAction::class)->execute($intake, $this->user->id);

        expect($this->ledger->quantity($this->shop->id, $variation->id))->toBe(4)
            ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(0)
            ->and(layers())->toBe([['intake', 9.0, 4]]);
    });

    it('applies stock adjustments to the shop, up as a layer and down from the oldest layers', function () {
        $this->ledger->receive($this->shop->id, $this->variation, 3, 10, StockMovementType::INTAKE);
        $adjust = function (string $type, int $quantity) {
            $adjustment = StockAdjustment::create([
                'uuid' => (string) Str::uuid(), 'adjustment_number' => 'ADJ-'.Str::random(5), 'shop_id' => $this->shop->id,
                'type' => $type, 'reason' => 'recount', 'status' => 'approved', 'created_by' => $this->user->id, 'approved_by' => $this->user->id,
            ]);
            StockAdjustmentItem::create([
                'uuid' => (string) Str::uuid(), 'stock_adjustment_id' => $adjustment->id, 'product_id' => $this->product->id,
                'shop_id' => $this->shop->id, 'quantity_before' => 0, 'quantity_change' => $quantity, 'quantity_after' => 0,
            ]);

            return app(StockAdjustmentService::class)->completeAdjustment($adjustment->fresh());
        };

        $adjust('increase', 4);
        $adjust('decrease', 5);

        // 3 at 10, then 4 at the latest layer's cost (10); 5 taken oldest first.
        expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(2)
            ->and(layers())->toBe([['intake', 10.0, 0], ['adjustment', 10.0, 2]])
            ->and(fn () => $adjust('decrease', 3))->toThrow(Exception::class, 'negative stock');
    });

    it('ships a purchase return from its own intake\'s layer first', function () {
        $supplier = Supplier::factory()->create();
        $older = StockIntake::factory()->pending()->create(['shop_id' => $this->shop->id, 'product_id' => $this->product->id, 'purchase_order_id' => null, 'purchase_order_item_id' => null, 'quantity_accepted' => 5]);
        $newer = StockIntake::factory()->pending()->create(['shop_id' => $this->shop->id, 'product_id' => $this->product->id, 'purchase_order_id' => null, 'purchase_order_item_id' => null, 'quantity_accepted' => 5]);
        $this->ledger->receive($this->shop->id, $this->variation, 5, 10, StockMovementType::INTAKE, ['stock_intake_id' => $older->id, 'received_at' => now()->subDays(2)]);
        $this->ledger->receive($this->shop->id, $this->variation, 5, 20, StockMovementType::INTAKE, ['stock_intake_id' => $newer->id, 'received_at' => now()->subDay()]);

        $return = PurchaseReturn::factory()->create(['shop_id' => $this->shop->id, 'supplier_id' => $supplier->id, 'status' => 'approved']);
        PurchaseReturnItem::factory()->create(['purchase_return_id' => $return->id, 'product_id' => $this->product->id, 'product_variation_id' => null, 'stock_intake_id' => $newer->id, 'quantity' => 2]);

        app(MarkPurchaseReturnShippedAction::class)->execute($return->fresh(), $this->user->id);

        expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(8)
            ->and(layers())->toBe([['intake', 10.0, 5], ['intake', 20.0, 3]]);
    });

    it('restocks a restockable customer return at the cost it was sold at, and nothing else', function () {
        $this->ledger->receive($this->shop->id, $this->variation, 4, 11, StockMovementType::INTAKE);
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'total_amount' => 300, 'tax_amount' => 0]);
        $line = SaleItem::create([
            'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
            'quantity' => 3, 'unit_price' => 100, 'line_total' => 300, 'taxable_amount' => 300, 'status' => 'completed',
        ]);
        $this->ledger->sellItem($line, $this->variation);

        $return = SaleReturn::create([
            'uuid' => (string) Str::uuid(), 'return_number' => 'RET-1', 'sale_id' => $sale->id, 'shop_id' => $this->shop->id,
            'reason' => 'defective', 'status' => 'approved', 'requested_by' => $this->user->id,
        ]);
        foreach ([[true, 2], [false, 1]] as [$restockable, $quantity]) {
            ReturnItem::create([
                'uuid' => (string) Str::uuid(), 'return_id' => $return->id, 'sale_item_id' => $line->id, 'product_id' => $this->product->id,
                'quantity' => $quantity, 'unit_price' => 100, 'total_price' => 100 * $quantity, 'is_restockable' => $restockable,
            ]);
        }

        app(ReturnService::class)->receiveReturn($return->fresh());

        // 4 − 3 sold + 2 restocked; the damaged one isn't.
        expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(3)
            ->and(layers())->toBe([['intake', 11.0, 1], ['sale_return', 11.0, 2]]);
    });
});

describe('readers', function () {
    beforeEach(function () {
        $this->otherShop = Shop::factory()->create();
        $this->ledger->receive($this->shop->id, $this->variation, 3, 10, StockMovementType::INTAKE);
        $this->ledger->receive($this->otherShop->id, $this->variation, 10, 10, StockMovementType::INTAKE);
    });

    it('raises low-stock alerts from the shop\'s own stock', function () {
        expect(app(CheckLowStockLevels::class)->execute($this->shop->id, $this->user->id)->pluck('current_quantity')->all())->toBe([3])
            ->and(app(CheckLowStockLevels::class)->execute($this->otherShop->id, $this->user->id))->toBeEmpty();
    });

    it('snapshots the shop\'s own stock at its FIFO value', function () {
        $snapshot = app(CreateInventorySnapshot::class)->execute($this->shop->id)->sole();

        expect($snapshot->quantity_on_hand)->toBe(3)
            ->and((float) $snapshot->total_value)->toBe(30.0);
    });

    it('reports a product\'s stock for the shop the API names, else across the viewer\'s shops', function () {
        $owner = User::factory()->create();
        $owner->assignRole(ownerRole());
        Sanctum::actingAs($owner, ['*']);

        $this->getJson("/api/products/{$this->product->uuid}?shop_id={$this->shop->id}")->assertJsonPath('data.stock_quantity', 3);
        $this->getJson("/api/products/{$this->product->uuid}")->assertJsonPath('data.stock_quantity', 13);
    });

    it('reports each listed shop\'s own stock in the flat API\'s pivot', function () {
        $owner = User::factory()->create();
        $owner->assignRole(ownerRole());
        $this->product->shops()->attach([$this->shop->id, $this->otherShop->id]);
        Sanctum::actingAs($owner, ['*']);

        $shops = collect($this->getJson("/api/products/{$this->product->uuid}")->json('data.shops'));

        expect($shops->mapWithKeys(fn ($shop) => [$shop['id'] => $shop['pivot']['stock_quantity']])->all())
            ->toBe([$this->shop->id => 3, $this->otherShop->id => 10]);
    });

    it('shows staff only the stock of their own shops', function () {
        $cashier = User::factory()->create();
        $cashier->shops()->attach($this->otherShop);
        $this->actingAs($cashier);

        expect($this->product->fresh()->stock_quantity)->toBe(10);
    });
});
