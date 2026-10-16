<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles per decision 4.4 (stage 3): the shared owner role `admin`, the role
 * templates each business gets its own copy of, and custom roles.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
});

it('gives a new business its own copy of each role template, with the template\'s permissions', function () {
    $business = Business::factory()->create();

    $copy = DB::table('roles')->where('business_id', $business->id)->where('name', 'cashier')->first();
    $template = DB::table('roles')->whereNull('business_id')->where('name', 'cashier')->first();

    expect($copy)->not->toBeNull()
        ->and(DB::table('role_has_permissions')->where('role_id', $copy->id)->pluck('permission_id')->sort()->values()->all())
        ->toBe(DB::table('role_has_permissions')->where('role_id', $template->id)->pluck('permission_id')->sort()->values()->all())
        // The owner role stays shared: owners always hold every permission.
        ->and(DB::table('roles')->where('business_id', $business->id)->where('name', 'admin')->exists())->toBeFalse();
});

it('uses the business\'s own copy in place of the template, in lookups and lists', function () {
    $business = Business::factory()->create();

    $this->tenant->run($business, function () use ($business): void {
        expect(Role::findByName('cashier')->business_id)->toBe($business->id)
            ->and(Role::where('name', 'cashier')->count())->toBe(1)
            ->and(Role::pluck('name')->all())->toContain('admin');
    });
});

it('applies a business\'s change to its cashier role, and a custom role, in that business only', function () {
    [$a, $b] = [Business::factory()->create(), Business::factory()->create()];
    $person = User::factory()->memberOf($a)->create();
    $person->joinBusiness($b);

    $this->tenant->run($a, function () use ($person): void {
        Role::findByName('cashier')->givePermissionTo('reports.full-access');
        Role::create(['name' => 'Stock Taker', 'guard_name' => 'web'])->givePermissionTo('stock-intake.create');
        $person->assignRole('cashier', 'Stock Taker');
    });
    $this->tenant->run($b, fn () => $person->assignRole('cashier'));

    $can = fn (Business $business, string $permission): bool => $this->tenant->run($business, fn () => $person->fresh()->hasPermissionTo($permission));

    expect($can($a, 'reports.full-access'))->toBeTrue()
        ->and($can($b, 'reports.full-access'))->toBeFalse()
        ->and($can($a, 'stock-intake.create'))->toBeTrue()
        ->and($this->tenant->run($b, fn () => Role::where('name', 'Stock Taker')->exists()))->toBeFalse();
});

it('grants a permission rolled out later to every business\'s copies, but not one a business removed', function () {
    $business = Business::factory()->create();
    $copy = fn () => $this->tenant->run($business, fn () => Role::findByName('manager'));

    // The business takes a permission away from its managers.
    $copy()->revokePermissionTo('reports.full-access');

    // A permission created later, granted to the template by the seeder.
    $this->travel(1)->minutes();
    Permission::create(['name' => 'brand-new.view', 'guard_name' => 'web']);
    $this->tenant->withoutBusiness(fn () => Role::findByName('manager')->givePermissionTo('brand-new.view'));
    $this->seed(RoleSeeder::class);

    expect($copy()->fresh()->hasPermissionTo('brand-new.view'))->toBeTrue()
        ->and($copy()->fresh()->hasPermissionTo('reports.full-access'))->toBeFalse();
});

it('makes the owners admins and moves template assignments onto the business\'s copies when migrated', function () {
    $business = Business::factory()->create();
    $owner = User::factory()->memberOf($business)->create();
    $cashier = User::factory()->memberOf($business)->create();

    // The layout before stage 3: a shared super-admin role, template assignments, no copies.
    DB::table('roles')->where('business_id', $business->id)->delete();
    DB::table('roles')->whereNull('business_id')->where('name', 'admin')->update(['name' => 'super-admin']);
    $template = fn (string $name) => DB::table('roles')->whereNull('business_id')->where('name', $name)->value('id');
    foreach ([[$owner, 'super-admin'], [$cashier, 'cashier']] as [$user, $role]) {
        DB::table('model_has_roles')->insert(['role_id' => $template($role), 'model_type' => User::class, 'model_id' => $user->id, 'business_id' => $business->id]);
    }

    $migration = require database_path('migrations/2026_10_16_000001_split_platform_operator_and_business_owner.php');
    // No schema change here: on MySQL it would commit the test's transaction
    // and make the next test rebuild the database (about a minute).
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->tenant->run($business, function () use ($owner, $cashier, $business): void {
        expect($owner->fresh()->isBusinessOwner())->toBeTrue()
            ->and($cashier->fresh()->roles->sole()->business_id)->toBe($business->id);
    });
});
