<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Profit from FIFO cost, and sales:recompute-profit on top of it (stage 1b).
 */
beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create();
});

/**
 * A sale of $quantity at 100 each, its line taking stock through the ledger.
 */
function fifoSale(Product $product, Shop $shop, int $quantity): SaleItem
{
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'total_amount' => 100 * $quantity, 'tax_amount' => 0, 'total_cost' => 0, 'total_profit' => 0]);
    $item = SaleItem::create([
        'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $product->id, 'shop_id' => $shop->id,
        'quantity' => $quantity, 'unit_price' => 100, 'line_total' => 100 * $quantity, 'taxable_amount' => 100 * $quantity, 'status' => 'completed',
    ]);
    app(StockLedger::class)->sellItem($item, $product->defaultVariation);
    app(StockLedger::class)->recostSale($sale->id);

    return $item;
}

it('keeps a sale\'s FIFO cost when the product\'s cost changes later', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'cost_price' => 30]);
    $this->ledger->receive($this->shop->id, $product->defaultVariation, 5, 30, StockMovementType::INTAKE);
    $item = fifoSale($product, $this->shop, 2);

    $product->update(['cost_price' => 45]);
    $this->artisan('sales:recompute-profit', ['--apply' => true])->assertSuccessful();

    expect((float) $item->fresh()->unit_cost)->toBe(30.0)
        ->and((float) $item->sale->fresh()->total_profit)->toBe(140.0);
});

it('costs stock of unknown cost once the purchase cost is entered, including the rest of its layer', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'cost_price' => null]);
    $this->ledger->receive($this->shop->id, $product->defaultVariation, 5, null, StockMovementType::INTAKE);
    $item = fifoSale($product, $this->shop, 2);
    expect((float) $item->fresh()->total_cost)->toBe(0.0);

    // A dry run, and a run while the cost is still unknown, change nothing.
    $this->artisan('sales:recompute-profit')->assertSuccessful();
    $this->artisan('sales:recompute-profit', ['--apply' => true])->assertSuccessful();
    expect((float) $item->fresh()->total_cost)->toBe(0.0);

    $product->update(['cost_price' => 25]);
    $product->defaultVariation->update(['cost_price' => 25]);
    $this->artisan('sales:recompute-profit', ['--apply' => true])->assertSuccessful();

    expect((float) $item->fresh()->unit_cost)->toBe(25.0)
        ->and((float) $item->sale->fresh()->total_profit)->toBe(150.0)
        ->and((float) DB::table('cost_layers')->where('product_id', $product->id)->value('unit_cost'))->toBe(25.0);

    // The next sale from that layer is costed straight away.
    expect((float) fifoSale($product, $this->shop, 1)->fresh()->unit_cost)->toBe(25.0);
});
