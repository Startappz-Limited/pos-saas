<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * For tables holding both shared rows (business_id NULL) and rows of one
 * business, such as roles: the shared role templates every business assigns,
 * plus each business's own roles. Shows the shared rows and the current
 * business's; with no business set, only the shared ones.
 */
class BusinessOrSharedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = app(TenantContext::class);

        if ($tenant->bypassing()) {
            return;
        }

        $column = $model->qualifyColumn('business_id');

        $builder->where(fn (Builder $query) => $query->whereNull($column)
            ->when($tenant->has(), fn (Builder $query) => $query->orWhere($column, $tenant->id())));
    }
}
