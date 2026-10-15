<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a shop-owned model to the shops the viewer may access in the
 * current business (decisions 4.1 and 4.4).
 *
 * A nullable shop column (alerts, campaigns, saved reports) also lets through
 * rows with no shop; those models carry business_id and BusinessScope, so the
 * shop-less rows stay within the business.
 */
class ShopAccessScope implements Scope
{
    public function __construct(
        private string $column = 'shop_id',
        private bool $nullable = false,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->bypassing() || ! $tenant->guardMissing($builder, $model)) {
            return;
        }

        $column = $model->qualifyColumn($this->column);
        // A subquery, except on shops itself: MySQL refuses an update whose
        // subquery reads the table being updated.
        $shopIds = $model->getTable() === 'shops'
            ? $tenant->accessibleShopIdsFor($tenant->viewer())
            : $tenant->accessibleShopsQuery($tenant->viewer());

        // Unordered shop queries used to come back in id order (a full scan
        // follows the primary key); the access filter can make MySQL read
        // through another index instead. The ~30 `Shop::first()` fallbacks
        // rely on that order, so keep it unless the query sets its own.
        if ($model->getTable() === 'shops' && empty($builder->getQuery()->orders)) {
            $builder->orderBy($model->qualifyColumn($model->getKeyName()));
        }

        if ($this->nullable) {
            $builder->where(fn (Builder $query) => $query->whereIn($column, $shopIds)->orWhereNull($column));
        } else {
            $builder->whereIn($column, $shopIds);
        }
    }
}
