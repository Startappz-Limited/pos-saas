<?php

namespace App\Actions\Product\Concerns;

use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\Log;

/**
 * Sets a variation's stock in the product's own shop at the cost entered
 * (stage 11, step 4): source_type 'opening', so leaving the cost blank
 * records it unknown rather than silently falling back to the shop's last
 * cost (what the old `stock_quantity` setter on the model did).
 */
trait AppliesOpeningStock
{
    private function applyOpeningStock(Product $product, ProductVariation $variation, int|float|string|null $quantity, ?float $cost): void
    {
        if ($quantity === null || $quantity === '') {
            return;
        }

        $shopId = $product->stockShopId();
        if ($shopId === null) {
            Log::warning('Stock figure ignored: no shop to hold it.', ['model' => Product::class, 'id' => $product->getKey()]);

            return;
        }

        app(StockLedger::class)->setLevel(
            $shopId,
            $variation,
            $quantity,
            ['reference' => $product, 'notes' => 'Stock figure set on the product.', 'source_type' => CostLayer::SOURCE_OPENING],
            $cost,
        );
    }
}
