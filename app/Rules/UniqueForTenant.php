<?php

namespace App\Rules;

use App\Support\Tenancy\MissingTenantContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A value unique within the current business (stage 1a): another business
 * may use the same code, and a soft-deleted record's code may be reused.
 * Mirrors the per-business unique indexes, which remain the guarantee.
 *
 *     new UniqueForTenant(Supplier::class, 'code')
 *     (new UniqueForTenant(Product::class, 'sku'))->ignore($product)
 */
class UniqueForTenant implements ValidationRule
{
    private mixed $ignoreId = null;

    /**
     * @param  class-string<Model>  $model
     */
    public function __construct(
        private string $model,
        private string $column,
    ) {}

    /**
     * Ignores $record (a model or its primary key), as when updating it.
     */
    public function ignore(Model|int|string|null $record): static
    {
        $this->ignoreId = $record instanceof Model ? $record->getKey() : $record;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $businessId = app(TenantContext::class)->id() ?? throw MissingTenantContext::forModel(class_basename($this->model));

        /** @var Model $model */
        $model = new $this->model;

        // A plain query: unlike the model's scopes, it sees rows in shops the
        // user can't access, which the business-wide key still covers.
        $taken = DB::connection($model->getConnectionName())->table($model->getTable())
            ->where('business_id', $businessId)
            ->where($this->column, $value)
            ->when(in_array(SoftDeletes::class, class_uses_recursive($model), true), fn ($query) => $query->whereNull($model->getDeletedAtColumn()))
            ->when($this->ignoreId !== null, fn ($query) => $query->where($model->getKeyName(), '!=', $this->ignoreId))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
