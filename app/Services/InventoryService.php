<?php

namespace App\Services;

use App\Actions\CreateInventorySnapshot;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class InventoryService
{
    public function __construct(
        protected CreateInventorySnapshot $createSnapshot
    ) {}

    /**
     * Get all inventory snapshots with pagination.
     */
    public function getAllSnapshots(int $perPage = 15): LengthAwarePaginator
    {
        return InventorySnapshot::with(['shop', 'product', 'variation'])
            ->latest('snapshot_date')
            ->paginate($perPage);
    }

    /**
     * Get snapshots for a specific shop.
     */
    public function getShopSnapshots(int $shopId, int $perPage = 15): LengthAwarePaginator
    {
        return InventorySnapshot::with(['product', 'variation'])
            ->forShop($shopId)
            ->latest('snapshot_date')
            ->paginate($perPage);
    }

    /**
     * Get snapshots for a specific product.
     */
    public function getProductSnapshots(int $productId, int $perPage = 15): LengthAwarePaginator
    {
        return InventorySnapshot::with(['shop', 'variation'])
            ->forProduct($productId)
            ->latest('snapshot_date')
            ->paginate($perPage);
    }

    /**
     * Get snapshot by UUID.
     */
    public function getSnapshotByUuid(string $uuid): ?InventorySnapshot
    {
        return InventorySnapshot::with(['shop', 'product', 'variation'])
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * Create snapshots for a shop.
     */
    public function createSnapshots(int $shopId, ?string $date = null, ?int $userId = null): Collection
    {
        return $this->createSnapshot->execute($shopId, $date, $userId);
    }

    /**
     * Get inventory valuation report.
     */
    public function getValuationReport(int $shopId, ?string $date = null): array
    {
        $snapshotDate = $date ?? now()->toDateString();

        $snapshots = InventorySnapshot::forShop($shopId)
            ->forDate($snapshotDate)
            ->get();

        return [
            'snapshot_date' => $snapshotDate,
            'total_items' => $snapshots->count(),
            'total_quantity' => $snapshots->sum('quantity_on_hand'),
            'total_cost_value' => $snapshots->sum('total_value'),
            'total_retail_value' => $snapshots->sum('retail_value'),
            'potential_profit' => $snapshots->sum('retail_value') - $snapshots->sum('total_value'),
            'snapshots' => $snapshots,
        ];
    }

    /**
     * Get current inventory levels.
     */
    public function getCurrentInventory(int $shopId): Collection
    {
        $products = Product::where('track_stock', true)->get();
        $inventory = collect();

        foreach ($products as $product) {
            if ($product->has_variations) {
                $variations = ProductVariation::where('product_id', $product->id)->get();
                foreach ($variations as $variation) {
                    $inventory->push([
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'variation_id' => $variation->id,
                        'variation_name' => $variation->name,
                        'sku' => $variation->sku,
                        'quantity' => $variation->stock_quantity,
                        'reorder_level' => $variation->reorder_level,
                        'is_low_stock' => $variation->stock_quantity <= $variation->reorder_level,
                        'cost_price' => $variation->cost_price,
                        'selling_price' => $variation->selling_price,
                        'total_cost_value' => $variation->stock_quantity * $variation->cost_price,
                        'total_retail_value' => $variation->stock_quantity * $variation->selling_price,
                    ]);
                }
            } else {
                $inventory->push([
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variation_id' => null,
                    'variation_name' => null,
                    'sku' => $product->sku,
                    'quantity' => $product->stock_quantity,
                    'reorder_level' => $product->reorder_level,
                    'is_low_stock' => $product->stock_quantity <= $product->reorder_level,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'total_cost_value' => $product->stock_quantity * $product->cost_price,
                    'total_retail_value' => $product->stock_quantity * $product->selling_price,
                ]);
            }
        }

        return $inventory;
    }

    /**
     * Get stock movement history for a product.
     */
    public function getProductMovementHistory(int $productId, ?int $variationId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = \App\Models\StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->forProduct($productId);

        if ($variationId) {
            $query->where('variation_id', $variationId);
        }

        return $query->recent()->paginate($perPage);
    }
}
