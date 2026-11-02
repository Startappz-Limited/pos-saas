<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * Cashiers may add a customer (decided 2 November 2026, hotfix fix/api-authz),
 * and nothing more about customers. The migration reaches the shared template
 * and every business's copy, which the seeder can't on an existing database
 * (stage 10, gap 11).
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
    [$this->a, $this->b] = [Business::factory()->create(), Business::factory()->create()];
    $this->tenant->forget();

    $this->template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'cashier')->firstOrFail();
    $this->copies = Role::withoutGlobalScopes()->where('template_id', $this->template->id)->get();
    $this->create = Permission::findByName('customers.create');
});

/**
 * Each cashier role's permission names, by role id.
 *
 * @return array<int, list<string>>
 */
function cashierGrants(): array
{
    return Role::withoutGlobalScopes()->where('name', 'cashier')->get()
        ->mapWithKeys(fn (Role $role): array => [$role->id => $role->permissions()->pluck('name')->sort()->values()->all()])->all();
}

it('gives the cashier template and every business\'s copy customers.create, adds nothing else, and is idempotent', function () {
    // An existing database: the permission is on no cashier role yet, and business A took
    // sales.refund away from its copy, which must stay away.
    DB::table('role_has_permissions')->where('permission_id', $this->create->id)
        ->whereIn('role_id', $this->copies->pluck('id')->push($this->template->id))->delete();
    $copyA = $this->copies->firstWhere('business_id', $this->a->id);
    $copyA->revokePermissionTo('sales.refund');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $before = cashierGrants();

    $migration = require database_path('migrations/2026_11_02_000001_grant_cashiers_customers_create.php');
    $migration->up();
    $once = cashierGrants();
    $migration->up();

    expect(cashierGrants())->toBe($once)
        ->and(array_keys($once))->toContain($this->template->id)->toHaveCount($this->copies->count() + 1);

    foreach ($once as $roleId => $names) {
        expect($names)->toContain('customers.create')
            ->and(array_values(array_diff($names, ['customers.create'])))->toBe($before[$roleId]);
    }
    expect($once[$copyA->id])->not->toContain('sales.refund');
});

it('lets a cashier add a customer through the API, and still refuses editing one', function () {
    $user = $this->tenant->run($this->a, function (): User {
        $user = User::factory()->memberOf($this->a)->create();
        $user->assignRole('cashier');
        $user->shops()->attach(Shop::factory()->create());

        return $user;
    });
    $this->tenant->forget();
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/customers', ['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'customer_type' => 'retail'])
        ->assertSuccessful();

    $customer = $this->tenant->run($this->a, fn () => Customer::query()->where('name', 'Wanjiru Kamau')->firstOrFail());
    $this->tenant->forget();

    $this->putJson("/api/customers/{$customer->getRouteKey()}", ['name' => 'Changed'])->assertForbidden();
});

it('gives cashiers no other customer, expense or online-order write (decided)', function () {
    foreach (['customers.update', 'customers.delete', 'customers.activate', 'customers.deactivate',
        'expenses.create', 'expenses.update', 'ecommerce-orders.update', 'sales.void', 'sales.delete'] as $name) {
        expect($this->template->hasPermissionTo($name))->toBeFalse($name);
    }
});
