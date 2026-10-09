<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Reads and reports on the purchase cost (COGS basis) of products.
 *
 * Purchase cost is only authoritative when it comes from a supplier — either a
 * purchase order captured through inventory intake, or a figure keyed in by
 * someone holding `products.set-cost`. Products that arrived from an
 * e-commerce platform have no such figure, which is what this service exists
 * to surface.
 */
class PurchaseCostService
{
    /**
     * Products whose purchase cost is unset (`missing`) or above their selling
     * price (`overpriced`), which is the fingerprint of a cost that was
     * derived from a price rather than a supplier invoice.
     */
    public function needingAttention(
        string $filter = 'all',
        ?string $search = null,
        ?int $categoryId = null,
        ?int $shopId = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        $query = Product::query()->with(['category', 'supplier', 'variations']);

        match ($filter) {
            'missing' => $query->missingPurchaseCost(),
            'overpriced' => $query->costAboveSellingPrice(),
            default => $query->where(function ($outer): void {
                $outer->missingPurchaseCost()
                    ->orWhere(fn ($inner) => $inner->costAboveSellingPrice());
            }),
        };

        if ($search) {
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->byCategory($categoryId);
        }

        if ($shopId) {
            $query->whereHas('shops', fn ($query) => $query->where('shops.id', $shopId));
        }

        return $query->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * The most recent unit cost each product was actually purchased at, keyed
     * by product id. Offered as a suggestion so products that DID come through
     * inventory intake can be filled in without re-keying supplier figures.
     *
     * @param  array<int, int>  $productIds
     * @return Collection<int, float>
     */
    public function suggestedCosts(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return PurchaseOrderItem::query()
            ->whereIn('product_id', $productIds)
            ->whereNull('product_variation_id')
            ->orderByDesc('id')
            ->get(['product_id', 'unit_cost'])
            ->groupBy('product_id')
            ->map(fn ($items) => (float) $items->first()->unit_cost);
    }

    /**
     * Headline counts for the purchase-cost screen.
     *
     * @return array{missing: int, overpriced: int, priced: int, total: int}
     */
    public function statistics(?int $shopId = null): array
    {
        $scope = fn () => Product::query()->when(
            $shopId,
            fn ($query) => $query->whereHas('shops', fn ($q) => $q->where('shops.id', $shopId))
        );

        $missing = $scope()->missingPurchaseCost()->count();
        $overpriced = $scope()->costAboveSellingPrice()->count();
        $total = $scope()->count();

        return [
            'missing' => $missing,
            'overpriced' => $overpriced,
            'priced' => max(0, $total - $missing),
            'total' => $total,
        ];
    }
}
