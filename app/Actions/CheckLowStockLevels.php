<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Collection;

class CheckLowStockLevels
{
    public function __construct(
        protected CreateLowStockAlert $createAlert
    ) {}

    /**
     * Check low stock levels for a shop and create alerts.
     */
    public function execute(int $shopId, ?int $userId = null): Collection
    {
        $alerts = collect();

        // Check simple products
        $products = Product::where('track_stock', true)
            ->where('has_variations', false)
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->get();

        foreach ($products as $product) {
            $alert = $this->createAlert->execute([
                'shop_id' => $shopId,
                'product_id' => $product->id,
                'variation_id' => null,
                'current_quantity' => $product->stock_quantity,
                'threshold_quantity' => $product->reorder_level,
                'created_by' => $userId ?? 1,
            ]);

            $alerts->push($alert);
        }

        // Check product variations. Variations have no reorder level of their
        // own: a variation is low when its stock is at or below its product's
        // reorder level. (Comparing with a bare `reorder_level` failed on MySQL
        // and MariaDB, where product_variations has no such column.)
        $variations = ProductVariation::with('product')
            ->whereHas('product', function ($query) {
                $query->where('track_stock', true)
                    ->where('has_variations', true)
                    ->whereColumn('product_variations.stock_quantity', '<=', 'products.reorder_level');
            })
            ->get();

        foreach ($variations as $variation) {
            $alert = $this->createAlert->execute([
                'shop_id' => $shopId,
                'product_id' => $variation->product_id,
                'variation_id' => $variation->id,
                'current_quantity' => $variation->stock_quantity,
                'threshold_quantity' => $variation->product->reorder_level,
                'created_by' => $userId ?? 1,
            ]);

            $alerts->push($alert);
        }

        return $alerts;
    }
}
