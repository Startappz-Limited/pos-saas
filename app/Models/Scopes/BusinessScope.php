<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits a business-owned model to the current business (decision 4.1).
 */
class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->bypassing() || ! $tenant->guardMissing($builder, $model)) {
            return;
        }

        $builder->where($model->qualifyColumn('business_id'), $tenant->id());
    }
}
