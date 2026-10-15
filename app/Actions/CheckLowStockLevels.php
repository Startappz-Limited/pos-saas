<?php

namespace App\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CheckLowStockLevels
{
    public function __construct(
        protected CreateLowStockAlert $createAlert
    ) {}

    /**
     * Checks the shop's stock and raises an alert for every item at or below
     * its reorder level (stage 1b: per-shop stock).
     *
     * A row's own reorder level (shop_variation) wins, else its product's. A
     * simple product is checked through its default variation and alerted
     * without a variation, as before; a product with variations is checked per
     * variation. Only this shop's stock counts.
     */
    public function execute(int $shopId, ?int $userId = null): Collection
    {
        $low = DB::table('shop_variation')
            ->join('products', 'products.id', '=', 'shop_variation.product_id')
            ->join('product_variations', 'product_variations.id', '=', 'shop_variation.variation_id')
            ->where('shop_variation.shop_id', $shopId)
            ->where('products.track_stock', true)
            ->whereNull('products.deleted_at')
            // Simple products through their default variation; the others per variation.
            ->whereRaw('product_variations.is_default = case when products.has_variations = 1 then 0 else 1 end')
            ->whereRaw('shop_variation.stock_quantity <= coalesce(shop_variation.reorder_level, products.reorder_level)')
            ->orderBy('shop_variation.id')
            ->get([
                'shop_variation.product_id',
                'shop_variation.variation_id',
                'shop_variation.stock_quantity',
                'product_variations.is_default',
                DB::raw('coalesce(shop_variation.reorder_level, products.reorder_level) as threshold'),
            ]);

        return $low->map(fn (object $row) => $this->createAlert->execute([
            'shop_id' => $shopId,
            'product_id' => $row->product_id,
            'variation_id' => $row->is_default ? null : $row->variation_id,
            'current_quantity' => (int) $row->stock_quantity,
            'threshold_quantity' => (int) $row->threshold,
            'created_by' => $userId ?? 1,
        ]));
    }
}
