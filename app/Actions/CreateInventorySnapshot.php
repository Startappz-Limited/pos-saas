<?php

namespace App\Actions;

use App\Models\InventorySnapshot;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateInventorySnapshot
{
    /**
     * Records the shop's stock of every tracked item (stage 1b: per-shop
     * stock). A simple product is recorded through its default variation,
     * without a variation, as before; a product with variations per variation.
     * The stock is valued at FIFO cost (what its remaining layers cost; an
     * unknown cost counts as zero) and at the selling price.
     */
    public function execute(int $shopId, ?string $date = null, ?int $userId = null): Collection
    {
        $snapshotDate = $date ?? now()->toDateString();
        $products = Product::where('track_stock', true)->get()->keyBy('id');

        $rows = DB::table('shop_variation')
            ->join('product_variations', 'product_variations.id', '=', 'shop_variation.variation_id')
            ->where('shop_variation.shop_id', $shopId)
            ->whereIn('shop_variation.product_id', $products->keys())
            ->get(['shop_variation.product_id', 'shop_variation.variation_id', 'shop_variation.stock_quantity', 'shop_variation.selling_price as shop_price', 'product_variations.selling_price', 'product_variations.is_default']);

        $fifoValue = DB::table('cost_layers')
            ->where('shop_id', $shopId)->where('remaining_quantity', '>', 0)
            ->groupBy('variation_id')
            ->selectRaw('variation_id, sum(remaining_quantity * coalesce(unit_cost, 0)) as value')
            ->pluck('value', 'variation_id');

        return $rows
            ->filter(fn (object $row): bool => (bool) $row->is_default === ! $products[$row->product_id]->has_variations)
            ->map(function (object $row) use ($products, $fifoValue, $shopId, $snapshotDate, $userId): InventorySnapshot {
                $product = $products[$row->product_id];
                $price = (float) ($row->shop_price ?? ($row->is_default ? $product->selling_price : $row->selling_price));
                $quantity = (int) $row->stock_quantity;

                return $this->createSnapshot([
                    'shop_id' => $shopId,
                    'product_id' => $product->id,
                    'variation_id' => $row->is_default ? null : $row->variation_id,
                    'quantity_on_hand' => $quantity,
                    'total_value' => round((float) ($fifoValue[$row->variation_id] ?? 0), 2),
                    'retail_value' => round(max($quantity, 0) * $price, 2),
                    'snapshot_date' => $snapshotDate,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            })
            ->values();
    }

    /**
     * Create a single inventory snapshot.
     */
    protected function createSnapshot(array $data): InventorySnapshot
    {
        return InventorySnapshot::updateOrCreate(
            [
                'shop_id' => $data['shop_id'],
                'product_id' => $data['product_id'],
                'variation_id' => $data['variation_id'],
                'snapshot_date' => $data['snapshot_date'],
            ],
            $data
        );
    }
}
