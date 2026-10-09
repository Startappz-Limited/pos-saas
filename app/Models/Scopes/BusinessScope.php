<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a business-owned model (suppliers, categories, ...) to the signed-in
 * user's business. Same unscoped cases as ShopAccessScope.
 */
class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = ShopAccessScope::currentUser();

        if ($user === null || $user->isSuperAdmin()) {
            return;
        }

        $businessId = $user->currentBusinessId();

        if ($businessId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('business_id'), $businessId);
    }
}
