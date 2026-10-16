<?php

use App\Models\AbandonedCart;
use App\Models\Attribute;
use App\Models\BaileysSession;
use App\Models\Campaign;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PricingRule;
use App\Models\PurchaseOrder;
use App\Models\SocialAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * The resource matrix (Phase 2): every resource route that names a record,
 * on the web and the flat API, refuses business B's record to business A's
 * owner (who passes every policy, so only the tenant boundary stands in the
 * way): show, update and delete are 404, and the record is unchanged.
 * `/api/v1` and sync rows are added when those endpoints are built (Phase 6).
 *
 * Every new model, route or endpoint adds its row here.
 */
beforeEach(function () {
    // Only the owner acts here: the fixture's owner role brings every permission.
    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();
});

/**
 * Business B's record for a resource, created in B.
 */
function recordOfB(TwoBusinesses $fixture, string $resource): Model
{
    $b = $fixture->b;
    $shop = ['shop_id' => $b->shops[0]->id];

    return app(TenantContext::class)->run($b->business, fn (): Model => match ($resource) {
        'abandoned-carts' => AbandonedCart::factory()->create($shop),
        'attributes' => Attribute::factory()->create(),
        'baileys.sessions' => BaileysSession::factory()->create($shop),
        'campaigns' => Campaign::factory()->create($shop),
        'cash-registers' => CashRegister::factory()->create([...$shop, 'user_id' => $b->owner->id]),
        'categories' => Category::factory()->create(),
        'credit-accounts' => CreditAccount::factory()->create(['customer_id' => $b->customer->id, ...$shop]),
        'customers' => $b->customer,
        'delivery-companies' => DeliveryCompany::factory()->create(),
        'ecommerce-orders' => EcommerceOrder::factory()->create($shop),
        'expense-categories' => ExpenseCategory::factory()->create(),
        'expenses' => Expense::factory()->create($shop),
        'pricing-rules' => PricingRule::factory()->create(),
        'products' => $b->product,
        'purchase-orders' => PurchaseOrder::factory()->create([...$shop, 'supplier_id' => $b->supplier->id]),
        'sales' => $b->sale,
        'shops' => $b->shops[1],
        'social-accounts' => SocialAccount::factory()->create($shop),
        'suppliers' => $b->supplier,
        'users' => $b->cashier,
    });
}

/** Resource => the actions its web routes offer on one record. */
const WEB_RESOURCES = [
    'abandoned-carts' => ['show', 'update'],
    'attributes' => ['show', 'update', 'destroy'],
    'baileys.sessions' => ['show', 'destroy'],
    'campaigns' => ['show', 'update', 'destroy'],
    'cash-registers' => ['show'],
    'categories' => ['show', 'update', 'destroy'],
    'credit-accounts' => ['show', 'update', 'destroy'],
    'customers' => ['show', 'update', 'destroy'],
    'ecommerce-orders' => ['show'],
    'expense-categories' => ['show', 'update', 'destroy'],
    'expenses' => ['show', 'update', 'destroy'],
    'pricing-rules' => ['show', 'update', 'destroy'],
    'products' => ['show', 'update', 'destroy'],
    'purchase-orders' => ['show', 'update', 'destroy'],
    'sales' => ['show', 'update', 'destroy'],
    'shops' => ['show', 'update', 'destroy'],
    // show: SocialAccountController::show doesn't exist (already on main).
    'social-accounts' => ['update', 'destroy'],
    'suppliers' => ['show', 'update', 'destroy'],
    'users' => ['show', 'update', 'destroy'],
];

/** Resource => the actions its flat API routes offer on one record. */
const API_RESOURCES = [
    'abandoned-carts' => ['show', 'update'],
    'cash-registers' => ['show'],
    'categories' => ['show', 'update', 'destroy'],
    'customers' => ['show', 'update', 'destroy'],
    'delivery-companies' => ['show', 'update', 'destroy'],
    'ecommerce-orders' => ['show'],
    'expense-categories' => ['show', 'update', 'destroy'],
    'expenses' => ['show', 'update', 'destroy'],
    'products' => ['show', 'update', 'destroy'],
    'purchase-orders' => ['show', 'update', 'destroy'],
    'sales' => ['show', 'update', 'destroy'],
    'shops' => ['show', 'update', 'destroy'],
    'suppliers' => ['show', 'update', 'destroy'],
    'users' => ['show', 'update', 'destroy'],
];

/**
 * @return list<array{0: string, 1: string, 2: string}>
 */
function matrixRows(): array
{
    $rows = [];
    foreach (['web' => WEB_RESOURCES, 'api' => API_RESOURCES] as $channel => $resources) {
        foreach ($resources as $resource => $actions) {
            foreach ($actions as $action) {
                $rows["{$channel} {$resource}.{$action}"] = [$channel, $resource, $action];
            }
        }
    }

    return $rows;
}

it('refuses business B\'s record to business A', function (string $channel, string $resource, string $action) {
    $record = recordOfB($this->fixture, $resource);
    $before = $record->fresh()?->getAttributes();
    $route = ($channel === 'api' ? 'api.' : '').$resource.'.'.$action;

    $channel === 'api' ? Sanctum::actingAs($this->fixture->a->owner, ['*']) : $this->actingAs($this->fixture->a->owner);
    // The route's own method (an update may be PUT or PATCH).
    $method = strtolower(collect(Route::getRoutes()->getByName($route)->methods())->reject(fn ($m) => $m === 'HEAD')->first());
    $call = $channel === 'api' ? $method.'Json' : $method;

    $response = $this->{$call}(route($route, $record), $action === 'update' ? ['name' => 'Taken over'] : []);

    expect($response->getStatusCode())->toBe(404, "{$route} answered {$response->getStatusCode()} ".substr((string) $response->exception?->getMessage(), 0, 200));

    // Unchanged and still there (read in B, past A's scope).
    $after = app(TenantContext::class)->run($this->fixture->b->business, fn () => $record->newQueryWithoutScopes()->find($record->getKey())?->getAttributes());
    expect($after)->toBe($before);
})->with(matrixRows());

it('rejects business B\'s ids on create', function (string $route, Closure $payload) {
    $this->actingAs($this->fixture->a->owner);
    $response = $this->post(route($route), $payload($this->fixture->b));

    $response->assertSessionHasErrors();
})->with([
    'expense in B\'s shop' => ['expenses.store', fn ($b) => ['shop_id' => $b->shops[0]->id, 'title' => 'x', 'amount' => 10, 'expense_date' => now()->toDateString()]],
    'product in B\'s shop' => ['products.store', fn ($b) => ['shop_id' => $b->shops[0]->id, 'name' => 'x', 'selling_price' => 10]],
    'purchase order from B\'s supplier' => ['purchase-orders.store', fn ($b) => ['supplier_id' => $b->supplier->id, 'shop_id' => $b->shops[0]->id]],
    'sale to B\'s customer of B\'s product' => ['sales.store', fn ($b) => ['customer_id' => $b->customer->id, 'items' => [['product_id' => $b->product->id, 'quantity' => 1, 'price' => 10]]]],
]);
