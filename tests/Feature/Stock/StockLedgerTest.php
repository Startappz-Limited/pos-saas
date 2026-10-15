<?php

use App\Enums\StockMovementType;
use App\Models\CostAllocation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The shared stock service: per-shop stock, movements and FIFO cost layers
 * (stage 1b, docs/saas/fifo-analysis.md).
 */
beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => 40, 'stock_quantity' => 0]);
    $this->variation = $this->product->defaultVariation;
});

/**
 * A completed sale with one line for $quantity, ready for sellItem().
 */
function saleLine(Product $product, Shop $shop, int $quantity, float $price = 100): SaleItem
{
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'total_amount' => $price * $quantity, 'tax_amount' => 0]);

    return SaleItem::create([
        'uuid' => (string) Str::uuid(),
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'shop_id' => $shop->id,
        'quantity' => $quantity,
        'unit_price' => $price,
        'line_total' => $price * $quantity,
        'taxable_amount' => $price * $quantity,
        'status' => 'completed',
    ]);
}

/**
 * Layers always add up to stock: what layers hold equals stock above zero,
 * and provisional allocations cover stock below zero.
 */
function expectLayersMatchStock(StockLedger $ledger, Shop $shop, ProductVariation $variation): void
{
    $stock = $ledger->quantity($shop->id, $variation->id);
    $remaining = (int) DB::table('cost_layers')->where('shop_id', $shop->id)->where('variation_id', $variation->id)->sum('remaining_quantity');
    $provisional = (int) DB::table('cost_allocations')->where('shop_id', $shop->id)->where('variation_id', $variation->id)->where('is_provisional', true)->sum('quantity_allocated');

    expect($remaining)->toBe(max($stock, 0))
        ->and($provisional)->toBe(max(-$stock, 0));
}

it('receives stock into a shop as a cost layer, with a movement', function () {
    $change = $this->ledger->receive($this->shop->id, $this->variation, 10, 35.5, StockMovementType::INTAKE);

    $layer = DB::table('cost_layers')->sole();
    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(10)
        ->and((int) $layer->remaining_quantity)->toBe(10)
        ->and((float) $layer->unit_cost)->toBe(35.5)
        ->and((int) $layer->business_id)->toBe($this->shop->business_id)
        ->and($change->movement->movement_type)->toBe(StockMovementType::INTAKE)
        ->and($change->movement->quantity_before)->toBe(0)
        ->and($change->movement->quantity_after)->toBe(10);
});

it('costs a sale from the oldest layers first', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 3, 10, StockMovementType::INTAKE, ['received_at' => now()->subDays(2)]);
    $this->ledger->receive($this->shop->id, $this->variation, 5, 20, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $item = saleLine($this->product, $this->shop, 4);

    $change = $this->ledger->sellItem($item, $this->variation);
    $this->ledger->recostSale($item->sale_id);

    // 3 at 10 + 1 at 20.
    expect($change->totalCost)->toBe(50.0)
        ->and((float) $item->fresh()->unit_cost)->toBe(12.5)
        ->and((float) $item->fresh()->profit)->toBe(350.0)
        ->and((float) $item->sale->fresh()->total_cost)->toBe(50.0)
        ->and((float) $item->sale->fresh()->total_profit)->toBe(350.0)
        ->and(DB::table('cost_layers')->orderBy('id')->pluck('remaining_quantity')->map(fn ($q) => (int) $q)->all())->toBe([0, 4])
        ->and(StockMovement::where('movement_type', StockMovementType::SALE)->sole()->quantity_after)->toBe(4);
    expectLayersMatchStock($this->ledger, $this->shop, $this->variation);
});

it('keeps stock per shop', function () {
    $other = Shop::factory()->create();
    $this->ledger->receive($this->shop->id, $this->variation, 5, 10, StockMovementType::INTAKE);
    $this->ledger->receive($other->id, $this->variation, 2, 10, StockMovementType::INTAKE);

    $this->ledger->sellItem(saleLine($this->product, $other, 2), $this->variation);

    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(5)
        ->and($this->ledger->quantity($other->id, $this->variation->id))->toBe(0);
});

it('takes a preferred layer first, as a purchase return takes its own intake', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 5, 10, StockMovementType::INTAKE, ['received_at' => now()->subDays(2)]);
    $this->ledger->receive($this->shop->id, $this->variation, 5, 30, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $newest = (int) DB::table('cost_layers')->max('id');

    $change = $this->ledger->consume($this->shop->id, $this->variation, 2, StockMovementType::SUPPLIER_RETURN, saleLine($this->product, $this->shop, 2), [], $newest);

    expect($change->totalCost)->toBe(60.0)
        ->and((int) DB::table('cost_layers')->where('id', $newest)->value('remaining_quantity'))->toBe(3);
});

it('accepts a sale below zero, costing the shortfall at the last known cost', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 2, 10, StockMovementType::INTAKE);
    $item = saleLine($this->product, $this->shop, 5);

    $change = $this->ledger->sellItem($item, $this->variation);

    $provisional = CostAllocation::where('sale_item_id', $item->id)->where('is_provisional', true)->sole();
    expect($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(-3)
        ->and($provisional->quantity_allocated)->toBe(3)
        ->and((float) $provisional->unit_cost)->toBe(10.0)
        ->and($provisional->cost_layer_id)->toBeNull()
        ->and($change->totalCost)->toBe(50.0)
        ->and($change->movement->notes)->toContain('below zero');
    expectLayersMatchStock($this->ledger, $this->shop, $this->variation);
});

it('re-costs units sold below zero when stock arrives, and updates the sale', function () {
    $item = saleLine($this->product, $this->shop, 3);
    $this->ledger->sellItem($item, $this->variation); // at the product's cost, 40
    $this->ledger->recostSale($item->sale_id);
    expect((float) $item->fresh()->total_cost)->toBe(120.0);

    $this->ledger->receive($this->shop->id, $this->variation, 10, 25, StockMovementType::INTAKE);

    $allocation = CostAllocation::where('sale_item_id', $item->id)->sole();
    expect($allocation->is_provisional)->toBeFalse()
        ->and((float) $allocation->unit_cost)->toBe(25.0)
        ->and((float) $allocation->provisional_unit_cost)->toBe(40.0)
        ->and($allocation->settled_at)->not->toBeNull()
        ->and((float) $item->fresh()->total_cost)->toBe(75.0)
        ->and((float) $item->fresh()->profit)->toBe(225.0)
        ->and((float) $item->sale->fresh()->total_profit)->toBe(225.0)
        // 3 of the 10 units went to the earlier sale.
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(7)
        ->and((int) DB::table('cost_layers')->value('remaining_quantity'))->toBe(7);
    expectLayersMatchStock($this->ledger, $this->shop, $this->variation);
});

it('re-costs part of a shortfall when too little stock arrives', function () {
    $item = saleLine($this->product, $this->shop, 5);
    $this->ledger->sellItem($item, $this->variation);

    $this->ledger->receive($this->shop->id, $this->variation, 2, 30, StockMovementType::INTAKE);

    $allocations = CostAllocation::where('sale_item_id', $item->id)->orderBy('id')->get();
    expect($allocations->map(fn ($a) => [$a->quantity_allocated, (float) $a->unit_cost, $a->is_provisional])->all())
        ->toEqualCanonicalizing([[3, 40.0, true], [2, 30.0, false]])
        // 3 still provisional at 40, 2 settled at 30.
        ->and((float) $item->fresh()->total_cost)->toBe(180.0)
        ->and($this->ledger->quantity($this->shop->id, $this->variation->id))->toBe(-3);
    expectLayersMatchStock($this->ledger, $this->shop, $this->variation);
});

it('carries an unknown cost as unknown, counting it as zero for profit', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => null, 'stock_quantity' => 0]);
    $this->ledger->receive($this->shop->id, $product->defaultVariation, 4, null, StockMovementType::INTAKE);
    $item = saleLine($product, $this->shop, 2);

    $change = $this->ledger->sellItem($item, $product->defaultVariation);

    expect($change->costKnown)->toBeFalse()
        ->and($change->totalCost)->toBe(0.0)
        ->and((float) $item->fresh()->unit_cost)->toBe(0.0)
        ->and(CostAllocation::where('sale_item_id', $item->id)->sole()->unit_cost)->toBeNull();
});

it('uses the latest layer\'s cost as the fallback for new stock', function () {
    $this->ledger->receive($this->shop->id, $this->variation, 1, 12, StockMovementType::INTAKE, ['received_at' => now()->subDays(2)]);
    $this->ledger->receive($this->shop->id, $this->variation, 1, 18, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);

    expect($this->ledger->fallbackCost($this->shop->id, $this->variation))->toBe(18.0)
        ->and($this->ledger->fallbackCost(Shop::factory()->create()->id, $this->variation))->toBe(40.0);
});
