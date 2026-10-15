<?php

namespace App\Support\Tenancy;

use App\Models\EcommerceOrderItem;
use App\Models\PurchaseOrderItem;
use App\Models\ReturnItem;
use App\Models\SaleItem;
use App\Models\Scopes\BusinessScope;
use App\Models\Scopes\ShopAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Makes `exists:` (and `unique:`) rules viewer-aware (stage 1a): on a tenant
 * table the check runs through the model's tenant scopes, so an id from
 * another business, or from a shop the user can't access, fails validation
 * instead of being accepted. Soft-deleted rows still count, as before.
 *
 * Child tables with no scope of their own count only rows whose parent the
 * viewer can see; `users` counts members of the current business. Every other
 * table (permissions, roles, ...) is checked as before.
 *
 * For uniqueness within a business, use UniqueForTenant rather than unique:
 * a scoped count can't see rows in shops the user can't access.
 */
class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    /**
     * Unscoped child tables, counted through the parent relation.
     *
     * @var array<string, array{class-string<Model>, string}>
     */
    private const CHILD_TABLES = [
        'purchase_order_items' => [PurchaseOrderItem::class, 'purchaseOrder'],
        'sale_items' => [SaleItem::class, 'sale'],
        'return_items' => [ReturnItem::class, 'saleReturn'],
        'ecommerce_order_items' => [EcommerceOrderItem::class, 'ecommerceOrder'],
    ];

    /** @var array<string, class-string<Model>>|null tenant model by table */
    private static ?array $tenantModels = null;

    protected function table($table)
    {
        $tenant = app(TenantContext::class);

        if ($this->connection !== null || $tenant->bypassing()) {
            return parent::table($table);
        }

        $query = match (true) {
            isset(self::CHILD_TABLES[$table]) => $this->childQuery(...self::CHILD_TABLES[$table]),
            $table === 'users' => $this->membersQuery($tenant),
            isset(self::tenantModels()[$table]) => $this->modelQuery(self::tenantModels()[$table]),
            default => null,
        };

        return $query?->useWritePdo() ?? parent::table($table);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function modelQuery(string $model): QueryBuilder
    {
        return $model::query()->withoutGlobalScope(SoftDeletingScope::class)->toBase();
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function childQuery(string $model, string $parent): QueryBuilder
    {
        return $model::query()
            ->whereHas($parent, fn (Builder $query) => $query->withoutGlobalScope(SoftDeletingScope::class))
            ->toBase();
    }

    private function membersQuery(TenantContext $tenant): QueryBuilder
    {
        // With no business there are no members: fail closed.
        return User::query()
            ->whereHas('memberships', fn (Builder $query) => $query->where('business_id', $tenant->id() ?? 0))
            ->toBase();
    }

    /**
     * Models carrying a tenant scope, by table.
     *
     * @return array<string, class-string<Model>>
     */
    private static function tenantModels(): array
    {
        return self::$tenantModels ??= collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class): bool => is_subclass_of($class, Model::class))
            ->mapWithKeys(fn (string $class): array => [(new $class)->getTable() => $class])
            ->filter(function (string $class): bool {
                $scopes = array_keys((new $class)->getGlobalScopes());

                return in_array(BusinessScope::class, $scopes, true) || in_array(ShopAccessScope::class, $scopes, true);
            })
            ->all();
    }
}
