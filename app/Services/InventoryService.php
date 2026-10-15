<?php

namespace App\Services;

use App\Actions\CreateInventorySnapshot;
use App\Models\InventorySnapshot;
use App\Models\Product;
use App\Models\ShopVariation;
use App\Models\StockMovement;
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
     * The shop's current stock of every tracked item (stage 1b: per-shop
     * stock). A row's own reorder level and prices (shop_variation) win over
     * the variation's and the product's.
     */
    public function getCurrentInventory(int $shopId): Collection
    {
        $products = Product::where('track_stock', true)->get()->keyBy('id');

        return ShopVariation::query()
            ->where('shop_id', $shopId)
            ->whereIn('product_id', $products->keys())
            ->with('variation')
            ->get()
            ->filter(fn (ShopVariation $row): bool => $row->variation !== null && $row->variation->is_default === ! $products[$row->product_id]->has_variations)
            ->map(function (ShopVariation $row) use ($products): array {
                $product = $products[$row->product_id];
                $variation = $row->variation;
                $simple = $variation->is_default;
                $reorder = $row->reorder_level ?? $product->reorder_level;
                $cost = $row->cost_price ?? ($simple ? $product->cost_price : $variation->cost_price);
                $price = $row->selling_price ?? ($simple ? $product->selling_price : $variation->selling_price);

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'variation_id' => $simple ? null : $variation->id,
                    'variation_name' => $simple ? null : $variation->name,
                    'sku' => $simple ? $product->sku : $variation->sku,
                    'quantity' => $row->stock_quantity,
                    'reorder_level' => $reorder,
                    'is_low_stock' => $row->stock_quantity <= $reorder,
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'total_cost_value' => $row->stock_quantity * (float) $cost,
                    'total_retail_value' => $row->stock_quantity * (float) $price,
                ];
            })
            ->values();
    }

    /**
     * Get stock movement history for a product.
     */
    public function getProductMovementHistory(int $productId, ?int $variationId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = StockMovement::with(['shop', 'product', 'variation', 'creator'])
            ->forProduct($productId);

        if ($variationId) {
            $query->where('variation_id', $variationId);
        }

        return $query->recent()->paginate($perPage);
    }
}
