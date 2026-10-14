<?php

use App\Models\AuditLog;
use App\Models\BusinessMembership;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Scopes\BusinessScope;
use App\Models\Scopes\ShopAccessScope;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Every model whose table has shop_id or business_id carries a tenant scope
 * (stage 1a acceptance, decision 4.1). A model that may go without one is
 * listed here with the reason.
 */
const UNSCOPED_TENANT_MODELS = [
    // A sale line's shop_id is its product's shop, not the sale's (and empty
    // for converted online orders); lines are reached through their Sale.
    SaleItem::class => 'reached through its scoped sale',
    // A login's memberships span businesses by design; read through the user.
    BusinessMembership::class => 'a user\'s memberships across businesses',
    // On the separate audit connection; reads go through AuditService, and
    // audit rows gain business_id in stage 1c.
    AuditLog::class => 'audit connection; scoped in stage 1c',
];

/**
 * Business-wide models with a shop_id column that only records where they
 * came from: scoped by business, deliberately not by shop.
 */
const BUSINESS_WIDE_MODELS = [
    Customer::class,      // Open question 3: customers belong to the business
    CreditAccount::class, // one per customer, serving every shop
    Product::class,       // decision 4.2: products belong to the business
];

/**
 * @return list<class-string<Model>>
 */
function appModels(): array
{
    return collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract())
        ->values()
        ->all();
}

it('scopes every model that has shop_id or business_id', function () {
    $missing = [];

    foreach (appModels() as $class) {
        $model = new $class;
        $columns = Schema::connection($model->getConnectionName())->getColumnListing($model->getTable());
        $tenantOwned = in_array('shop_id', $columns, true) || in_array('business_id', $columns, true);

        if (! $tenantOwned || array_key_exists($class, UNSCOPED_TENANT_MODELS)) {
            continue;
        }

        $scopes = array_keys($model->getGlobalScopes());

        if (in_array('business_id', $columns, true) && ! in_array(BusinessScope::class, $scopes, true)) {
            $missing[] = "{$class}: has business_id but no BusinessScope";
        }

        if (in_array('shop_id', $columns, true) && ! in_array(ShopAccessScope::class, $scopes, true)
            && ! in_array($class, BUSINESS_WIDE_MODELS, true)) {
            $missing[] = "{$class}: has shop_id but no ShopAccessScope";
        }
    }

    expect($missing)->toBe([], "Tenant models without their scope:\n".implode("\n", $missing));
});

it('lists only models that still need an exception', function () {
    foreach (array_keys(UNSCOPED_TENANT_MODELS) as $class) {
        expect(array_keys((new $class)->getGlobalScopes()))
            ->not->toContain(BusinessScope::class)
            ->not->toContain(ShopAccessScope::class);
    }
});

it('binds every route model by uuid, never by a sequential id', function () {
    $sequential = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach ($route->signatureParameters(['subClass' => UrlRoutable::class]) as $parameter) {
            $class = $parameter->getType()?->getName();

            if (! $class || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            $model = new $class;
            $field = $route->bindingFieldFor($parameter->getName()) ?? $model->getRouteKeyName();
            // A uuid primary key (attributes) is as good as a uuid column.
            $byUuid = $field === 'uuid' || ($field === $model->getKeyName() && $model->getKeyType() === 'string');

            if (! $byUuid) {
                $sequential[] = "{$route->uri()}: {$class} by {$field}";
            }
        }
    }

    expect(array_unique($sequential))->toBe([], "Routes binding a model by a sequential id:\n".implode("\n", array_unique($sequential)));
});
