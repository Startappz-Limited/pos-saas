<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/*
 * The customer permissions rolled out to existing businesses (decided
 * 2 November 2026, hotfix fix/api-authz): managers get customers.create,
 * update, activate and deactivate; accountants create and update; cashiers
 * create (the earlier migration); inventory clerks nothing. Both migrations go
 * through GrantTemplatePermissions: add-only, idempotent, and every role that
 * gains a permission gets an audit entry, with no person as the actor.
 */

const CUSTOMER_GRANTS = [
    'manager' => ['customers.activate', 'customers.create', 'customers.deactivate', 'customers.update'],
    'accountant' => ['customers.create', 'customers.update'],
    'cashier' => ['customers.create'],
];

beforeEach(function () {
    migrateAuditDatabase();
    DB::connection('audit')->table('audit_logs')->delete();
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
    [$this->a, $this->b] = [Business::factory()->create(), Business::factory()->create()];
    $this->tenant->forget();

    // An existing database: no customer permission on any of these roles yet.
    $customerIds = Permission::query()->where('name', 'like', 'customers.%')->pluck('id');
    DB::table('role_has_permissions')->whereIn('permission_id', $customerIds)
        ->whereIn('role_id', Role::withoutGlobalScopes()->whereIn('name', ['manager', 'accountant', 'cashier', 'inventory-clerk'])->pluck('id'))
        ->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    DB::connection('audit')->table('audit_logs')->delete();
});

/**
 * Each role's permission names, by role id, for the given template name.
 *
 * @return array<int, list<string>>
 */
function grantsOf(string $name): array
{
    return Role::withoutGlobalScopes()->where('name', $name)->get()
        ->mapWithKeys(fn (Role $role): array => [$role->id => $role->permissions()->pluck('name')->sort()->values()->all()])->all();
}

function runCustomerMigrations(): void
{
    foreach (['2026_11_02_000001_grant_cashiers_customers_create.php', '2026_11_02_000002_grant_managers_accountants_customers.php'] as $file) {
        (require database_path("migrations/{$file}"))->up();
    }
}

it('gives each template and every business\'s copy exactly its customer permissions, adds nothing else, and is idempotent', function () {
    // Business A took products.view off its manager copy and sales.view off its accountant copy: they stay off.
    $managerA = Role::withoutGlobalScopes()->where('business_id', $this->a->id)->where('name', 'manager')->firstOrFail();
    $accountantA = Role::withoutGlobalScopes()->where('business_id', $this->a->id)->where('name', 'accountant')->firstOrFail();
    $managerA->revokePermissionTo('products.view');
    $accountantA->revokePermissionTo('sales.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $before = collect(array_keys(CUSTOMER_GRANTS))->mapWithKeys(fn (string $name) => [$name => grantsOf($name)])->all();
    $clerks = grantsOf('inventory-clerk');

    runCustomerMigrations();
    $once = collect(array_keys(CUSTOMER_GRANTS))->mapWithKeys(fn (string $name) => [$name => grantsOf($name)])->all();
    runCustomerMigrations();

    foreach (CUSTOMER_GRANTS as $name => $granted) {
        // The template and both businesses' copies.
        expect($once[$name])->toHaveCount(3)
            ->and(grantsOf($name))->toBe($once[$name]);

        foreach ($once[$name] as $roleId => $names) {
            expect(array_values(array_intersect($names, ['customers.activate', 'customers.create', 'customers.deactivate', 'customers.delete', 'customers.update'])))
                ->toBe($granted, "{$name} {$roleId}")
                ->and(array_values(array_diff($names, $granted)))->toBe($before[$name][$roleId]);
        }
    }

    expect($once['manager'][$managerA->id])->not->toContain('products.view')
        ->and($once['accountant'][$accountantA->id])->not->toContain('sales.view')
        ->and(grantsOf('inventory-clerk'))->toBe($clerks)
        ->and(collect($once['manager'])->flatten())->not->toContain('customers.delete');
});

it('audits every grant once, with no person as the actor, and nothing on a second run', function () {
    runCustomerMigrations();

    $rows = DB::connection('audit')->table('audit_logs')->where('auditable_type', Role::class)->get();
    $roles = Role::withoutGlobalScopes()->whereIn('name', array_keys(CUSTOMER_GRANTS))->get()->keyBy('id');

    // One entry per role that gained: three roles, each a template and two copies.
    expect($rows)->toHaveCount(9);
    foreach ($rows as $row) {
        $role = $roles[$row->auditable_id];
        $values = json_decode($row->new_values, true);
        expect($values['permissions_attached'])->toBe(CUSTOMER_GRANTS[$role->name])
            ->and($values['reason'])->toContain('2 November 2026')
            ->and($row->user_id)->toBeNull()
            ->and($row->business_id === null ? null : (int) $row->business_id)->toBe($role->business_id === null ? null : (int) $role->business_id);
    }

    runCustomerMigrations();
    expect(DB::connection('audit')->table('audit_logs')->where('auditable_type', Role::class)->count())->toBe(9);
});

it('lets a manager and an accountant add and edit a customer through the API, and refuses deleting one', function (string $role, bool $mayActivate) {
    runCustomerMigrations();
    $shop = $this->tenant->run($this->a, fn () => Shop::factory()->create());
    $user = $this->tenant->run($this->a, function () use ($role, $shop): User {
        $user = User::factory()->memberOf($this->a)->create();
        $user->assignRole($role);
        $user->shops()->attach($shop);

        return $user;
    });
    $this->tenant->forget();
    Sanctum::actingAs($user, ['*']);

    $created = $this->postJson('/api/customers', ['name' => 'Wanjiru Kamau', 'phone' => '0712345678', 'customer_type' => 'retail'])->assertCreated();
    $customer = $created->json('data.uuid');

    $this->putJson("/api/customers/{$customer}", ['name' => 'Wanjiru K.', 'phone' => '0712345678', 'customer_type' => 'retail'])->assertOk();
    $this->postJson("/api/customers/{$customer}/deactivate")->assertStatus($mayActivate ? 200 : 403);
    $this->deleteJson("/api/customers/{$customer}")->assertForbidden();
})->with([
    'manager' => ['manager', true],
    'accountant' => ['accountant', false],
]);
