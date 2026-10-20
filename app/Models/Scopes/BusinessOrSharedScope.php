<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * For tables holding both shared rows (business_id NULL) and rows of one
 * business, such as roles: the shared role templates, plus each business's own
 * roles. Shows the current business's rows, and the shared rows it has no own
 * row of the same name for (a business's copy of a template replaces it, stage
 * 3); with no business set, only the shared ones.
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
        $table = $model->getTable();

        $builder->where(fn (Builder $query) => $query
            ->where(fn (Builder $shared) => $shared->whereNull($column)
                ->when($tenant->has(), fn (Builder $shared) => $shared->whereNotExists(fn ($own) => $own->selectRaw('1')
                    ->from("{$table} as own_rows")
                    ->whereColumn('own_rows.name', $model->qualifyColumn('name'))
                    ->whereColumn('own_rows.guard_name', $model->qualifyColumn('guard_name'))
                    ->where('own_rows.business_id', $tenant->id()))))
            ->when($tenant->has(), fn (Builder $query) => $query->orWhere($column, $tenant->id())));
    }
}
