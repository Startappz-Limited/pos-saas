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

        // Check product variations
        $variations = ProductVariation::whereHas('product', function ($query) {
            $query->where('track_stock', true)->where('has_variations', true);
        })
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->get();

        foreach ($variations as $variation) {
            $alert = $this->createAlert->execute([
                'shop_id' => $shopId,
                'product_id' => $variation->product_id,
                'variation_id' => $variation->id,
                'current_quantity' => $variation->stock_quantity,
                'threshold_quantity' => $variation->reorder_level,
                'created_by' => $userId ?? 1,
            ]);

            $alerts->push($alert);
        }

        return $alerts;
    }
}
