<?php

namespace App\Services\Stock;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Reads per-shop stock (stage 1b, decision 4.8): a product's or variation's
 * stock is the shop named in the request when it names one the viewer may
 * access, otherwise the total across the shops the viewer may access in the
 * current business. With no business set (a job or command), every shop.
 */
class StockQuery
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * The shops a stock figure covers, as a subquery of ids.
     */
    public function shops(): Builder
    {
        if (! $this->tenant->has()) {
            return DB::table('shops')->select('shops.id');
        }

        $accessible = $this->tenant->accessibleShopsQuery($this->tenant->viewer());
        $named = (int) request()->query('shop_id', request()->input('shop_id', 0));

        return $named > 0 ? $accessible->where('shops.id', $named) : $accessible;
    }

    /**
     * Correlated subquery: the stock of the row's product or variation.
     *
     * @param  string  $key  shop_variation column to match (product_id, variation_id)
     * @param  string  $outer  qualified column of the outer row (products.id)
     */
    public function subquery(string $key, string $outer): Builder
    {
        return DB::table('shop_variation')
            ->selectRaw('coalesce(sum(shop_variation.stock_quantity), 0)')
            ->whereColumn("shop_variation.{$key}", $outer)
            ->whereIn('shop_variation.shop_id', $this->shops());
    }

    public function forProduct(int $productId): int
    {
        return (int) DB::table('shop_variation')->where('product_id', $productId)->whereIn('shop_id', $this->shops())->sum('stock_quantity');
    }

    public function forVariation(int $variationId): int
    {
        return (int) DB::table('shop_variation')->where('variation_id', $variationId)->whereIn('shop_id', $this->shops())->sum('stock_quantity');
    }
}
