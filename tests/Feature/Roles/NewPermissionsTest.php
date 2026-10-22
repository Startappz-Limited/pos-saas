<?php

use App\Models\Attribute;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Permission;

/**
 * The attribute and stock permissions, Phase 5 (A4): `attributes.view`,
 * `.create`, `.update`, `.delete` and `stock.view` exist; owners and
 * managers hold them after seeding (copies included), inventory clerks hold
 * `stock.view`; every attribute action needs its permission; the sidebar
 * entries show for the roles that hold them. Re-running the seeder doesn't
 * undo a template edit.
 */
const NEW_PERMISSIONS = ['attributes.view', 'attributes.create', 'attributes.update', 'attributes.delete', 'stock.view'];

beforeEach(function () {
    seedRolesAndPermissions();
    $this->tenant = app(TenantContext::class);
});

/**
 * @return array<int, string>
 */
function templatePermissions(string $name): array
{
    return Role::withoutGlobalScopes()->whereNull('business_id')->where('name', $name)->firstOrFail()
        ->permissions()->pluck('name')->all();
}

/**
 * @return array<int, string>
 */
function copyPermissions(Business $business, string $name): array
{
    return Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', $name)->firstOrFail()
        ->permissions()->pluck('name')->all();
}

it('gives owners and managers the new permissions, inventory clerks stock.view, and the customer template nothing new', function () {
    expect(Permission::query()->whereIn('name', NEW_PERMISSIONS)->count())->toBe(5)
        ->and(templatePermissions('admin'))->toContain(...NEW_PERMISSIONS)
        ->and(templatePermissions('manager'))->toContain(...NEW_PERMISSIONS)
        ->and(templatePermissions('inventory-clerk'))->toContain('stock.view')->not->toContain('attributes.view')
        ->and(templatePermissions('cashier'))->not->toContain('stock.view')
        ->and(collect(templatePermissions('customer'))->sort()->values()->all())->toBe(['dashboard.view', 'products.view']);
});

it('reaches the templates and every business\'s copies when rolled out on an existing install', function () {
    // An install from before Phase 5: the permissions don't exist yet.
    Permission::query()->whereIn('name', NEW_PERMISSIONS)->delete();
    $business = Business::factory()->create();
    expect(copyPermissions($business, 'manager'))->not->toContain('stock.view');

    // The rollout: PermissionSeeder then RoleSeeder.
    $this->travel(1)->minutes();
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(templatePermissions('admin'))->toContain(...NEW_PERMISSIONS)
        ->and(copyPermissions($business, 'manager'))->toContain(...NEW_PERMISSIONS)
        ->and(copyPermissions($business, 'inventory-clerk'))->toContain('stock.view')->not->toContain('attributes.view')
        ->and(copyPermissions($business, 'customer'))->not->toContain('stock.view');
});

it('keeps a template edit (as the platform console makes) when the seeder runs again', function () {
    $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'manager')->firstOrFail();

    $this->travel(1)->minutes();
    $template->revokePermissionTo('reports.full-access');
    $template->touch();

    $this->travel(1)->minutes();
    $this->seed(RoleSeeder::class);

    expect(templatePermissions('manager'))->not->toContain('reports.full-access');
});

it('refuses every attribute action without its permission, and allows it with', function () {
    $business = Business::factory()->create();
    [$cashier, $manager, $attribute] = $this->tenant->run($business, function () use ($business): array {
        $cashier = User::factory()->memberOf($business)->create();
        $cashier->assignRole('cashier');
        $manager = User::factory()->memberOf($business)->create();
        $manager->assignRole('manager');

        return [$cashier, $manager, Attribute::factory()->create()];
    });
    $payload = ['name' => 'Size', 'type' => 'dropdown'];
    $this->tenant->forget();

    $calls = [
        ['get', route('attributes.index'), []],
        ['get', route('attributes.create'), []],
        ['post', route('attributes.store'), $payload],
        ['get', route('attributes.show', $attribute), []],
        ['get', route('attributes.edit', $attribute), []],
        ['put', route('attributes.update', $attribute), $payload],
        ['post', route('attributes.activate', $attribute), []],
        ['post', route('attributes.deactivate', $attribute), []],
        ['delete', route('attributes.destroy', $attribute), []],
    ];

    foreach ($calls as [$method, $url, $data]) {
        $this->actingAs($cashier)->{$method}($url, $data)->assertForbidden();
        $this->tenant->forget();
    }
    expect($attribute->fresh())->not->toBeNull();

    $this->actingAs($manager)->get(route('attributes.index'))->assertOk();
    $this->tenant->forget();
    $this->actingAs($manager)->post(route('attributes.deactivate', $attribute))->assertRedirect();
    $this->tenant->forget();
    $this->actingAs($manager)->delete(route('attributes.destroy', $attribute))->assertRedirect(route('attributes.index'));
    expect($this->tenant->run($business, fn () => Attribute::query()->find($attribute->id)))->toBeNull();
});

it('shows the Attributes and Stock menu entries to the roles that hold them', function () {
    $business = Business::factory()->create();
    [$cashier, $manager] = $this->tenant->run($business, function () use ($business): array {
        $cashier = User::factory()->memberOf($business)->create();
        $cashier->assignRole('cashier');
        $manager = User::factory()->memberOf($business)->create();
        $manager->assignRole('manager');

        return [$cashier, $manager];
    });
    $this->tenant->forget();

    $this->actingAs($manager)->get(route('dashboard'))->assertOk()
        ->assertSee(route('attributes.index'), false)->assertSee(route('stock-intakes.index'), false);
    $this->tenant->forget();
    $this->actingAs($cashier)->get(route('dashboard'))->assertOk()
        ->assertDontSee(route('attributes.index'), false)->assertDontSee(route('stock-intakes.index'), false);
});

it('lets a manager create and edit an attribute (both failed before: no fillable fields)', function () {
    $business = Business::factory()->create();
    $manager = $this->tenant->run($business, function () use ($business): User {
        $manager = User::factory()->memberOf($business)->create();
        $manager->assignRole('manager');

        return $manager;
    });
    $this->tenant->forget();

    $this->actingAs($manager)->post(route('attributes.store'), ['name' => 'Colour', 'type' => 'color', 'values' => 'Red, Blue'])
        ->assertSessionHasNoErrors()->assertRedirect(route('attributes.index'));
    $attribute = $this->tenant->run($business, fn () => Attribute::query()->where('name', 'Colour')->firstOrFail());

    $this->tenant->forget();
    $this->actingAs($manager)->put(route('attributes.update', $attribute), ['name' => 'Colour', 'type' => 'button', 'is_active' => '1'])
        ->assertSessionHasNoErrors()->assertRedirect(route('attributes.index'));

    expect($attribute->fresh()->type)->toBe('button')
        ->and($attribute->fresh()->values)->toBe(['Red', 'Blue'])
        ->and($attribute->fresh()->business_id)->toBe($business->id);
});
