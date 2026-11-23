<?php

namespace App\Services\Stock;

use App\Casts\Quantity;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Support\Stock\SellableStock;
use App\Support\Stock\UnitBreakdown;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Is this low?", answered once (stage 11c, step 1): for one shop, in base units, the variations whose
 * SELLABLE stock (expired and quarantined batches excluded, SellableStock) is at or below the shop's reorder
 * level, else the product's. Tracked products only, and only products active in the shop.
 *
 * Every low-stock figure in the app — the Stock → Low stock report, the product filter, the dashboard, the
 * alerts and both low-stock API endpoints — comes from here, so they can't disagree.
 */
class LowStockQuery
{
    /**
     * The low variations of $shop, each as: product_id, variation_id, is_default, sellable (string, base units),
     * threshold (string), shortfall (string, never below zero), breakdown (sellable in the largest unit).
     *
     * @return Collection<int, array{product_id: int, variation_id: int, is_default: bool, sellable: string, threshold: string, shortfall: string, breakdown: string}>
     */
    public function forShop(Shop $shop): Collection
    {
        $candidates = DB::table('shop_variation')
            ->tap(fn ($query) => app(TenantContext::class)->restrictToShops($query, 'shop_variation.shop_id'))
            ->join('products', 'products.id', '=', 'shop_variation.product_id')
            ->join('product_variations', 'product_variations.id', '=', 'shop_variation.variation_id')
            ->join('shop_product', fn ($join) => $join->on('shop_product.product_id', '=', 'products.id')->on('shop_product.shop_id', '=', 'shop_variation.shop_id'))
            ->where('shop_variation.shop_id', $shop->id)
            ->where('products.track_stock', true)
            ->where('shop_product.is_active', true)
            ->whereNull('products.deleted_at')
            // Simple products through their default variation; the others per variation.
            ->whereRaw('product_variations.is_default = case when products.has_variations = 1 then 0 else 1 end')
            ->orderBy('shop_variation.id')
            ->get([
                'shop_variation.product_id',
                'shop_variation.variation_id',
                'product_variations.is_default',
                DB::raw('coalesce(shop_variation.reorder_level, products.reorder_level) as threshold'),
            ]);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $sellable = SellableStock::forShop($shop);

        return $candidates->map(function (object $row) use ($sellable): ?array {
            $figure = $sellable->get((int) $row->variation_id)['sellable'] ?? '0.000';
            $threshold = Quantity::normalize($row->threshold ?? 0);

            if (bccomp($figure, $threshold, 3) > 0) {
                return null;
            }

            $shortfall = bcsub($threshold, $figure, 3);

            return [
                'product_id' => (int) $row->product_id,
                'variation_id' => (int) $row->variation_id,
                'is_default' => (bool) $row->is_default,
                'sellable' => $figure,
                'threshold' => $threshold,
                'shortfall' => bccomp($shortfall, '0', 3) > 0 ? $shortfall : '0.000',
                'breakdown' => UnitBreakdown::describe(ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($row->variation_id), $figure),
            ];
        })->filter()->values();
    }

    /**
     * The low variations across the shops a list covers: the request's shop when it names one the viewer may
     * access, else every shop the viewer may access in the current business. Each row carries its shop_id.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forViewer(?int $shopId = null): Collection
    {
        $shops = Shop::query()
            ->whereIn('id', app(TenantContext::class)->accessibleShopsQuery(auth()->user()))
            ->when($shopId !== null, fn ($query) => $query->where('id', $shopId))
            ->get();

        return $shops->flatMap(fn (Shop $shop) => $this->forShop($shop)->map(fn (array $row): array => [...$row, 'shop_id' => $shop->id]))->values();
    }

    /**
     * Whether one variation of $shop is low now.
     */
    public function isLow(Shop $shop, int $variationId): bool
    {
        return $this->forShop($shop)->contains(fn (array $row): bool => $row['variation_id'] === $variationId);
    }
}
