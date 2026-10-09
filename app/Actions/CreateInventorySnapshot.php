<?php

namespace App\Actions;

use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Support\Collection;

class CreateInventorySnapshot
{
    /**
     * Create inventory snapshots for a shop.
     */
    public function execute(int $shopId, ?string $date = null, ?int $userId = null): Collection
    {
        $snapshotDate = $date ?? now()->toDateString();
        $snapshots = collect();

        // Get all products with track_stock enabled
        $products = Product::where('track_stock', true)->get();

        foreach ($products as $product) {
            if ($product->has_variations) {
                // Create snapshots for each variation
                $variations = ProductVariation::where('product_id', $product->id)->get();

                foreach ($variations as $variation) {
                    $snapshot = $this->createSnapshot([
                        'shop_id' => $shopId,
                        'product_id' => $product->id,
                        'variation_id' => $variation->id,
                        'quantity_on_hand' => $variation->stock_quantity,
                        'total_value' => $variation->stock_quantity * $variation->cost_price,
                        'retail_value' => $variation->stock_quantity * $variation->selling_price,
                        'snapshot_date' => $snapshotDate,
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]);

                    $snapshots->push($snapshot);
                }
            } else {
                // Create snapshot for simple product
                $snapshot = $this->createSnapshot([
                    'shop_id' => $shopId,
                    'product_id' => $product->id,
                    'variation_id' => null,
                    'quantity_on_hand' => $product->stock_quantity,
                    'total_value' => $product->stock_quantity * $product->cost_price,
                    'retail_value' => $product->stock_quantity * $product->selling_price,
                    'snapshot_date' => $snapshotDate,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $snapshots->push($snapshot);
            }
        }

        return $snapshots;
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
