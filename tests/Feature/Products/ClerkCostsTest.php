<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Register;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Till;

/*
 * Clerks count stock; they don't see or set costs (stage 10, step 1f). The flat
 * API's product endpoints follow ProductPolicy as the web does, return a
 * product's costs only to someone holding products.view-cost or
 * products.set-cost (the key kept, with null, for anyone else), take a cost only
 * from someone holding products.set-cost, and refuse products with variations
 * rather than making them simple. The till's sync pull carries no cost.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => 42.50, 'selling_price' => 100]);
    $this->member = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->shops()->attach($this->shop);

        return $user;
    };
});

it('gives neither the clerk nor the cashier template a cost permission', function () {
    foreach (['inventory-clerk', 'cashier'] as $name) {
        $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', $name)->firstOrFail();
        expect($template->hasPermissionTo('products.set-cost') || $template->hasPermissionTo('products.view-cost'))->toBeFalse($name);
    }
});

it('lets a clerk count and adjust stock, but not see or set a cost on the web', function () {
    $clerk = ($this->member)('inventory-clerk');
    expect($clerk->can('inventory.count'))->toBeTrue()
        ->and($clerk->can('stock-adjustments.create'))->toBeTrue();
    $this->actingAs($clerk);

    $this->get(route('products.purchase-costs.index'))->assertForbidden();
    $this->get(route('products.show', $this->product))->assertOk()->assertDontSee('42.50');
    $this->get(route('products.edit', $this->product))->assertOk()->assertDontSee('name="cost_price"', false);
});

it('lets a role the owner gave products.set-cost see and set costs', function () {
    $clerk = ($this->member)('inventory-clerk');
    $clerk->givePermissionTo('products.set-cost');
    $this->actingAs($clerk);

    $this->get(route('products.purchase-costs.index'))->assertOk();
});

it('refuses a cashier creating a product through the flat API', function () {
    Sanctum::actingAs(($this->member)('cashier'), ['*']);

    $this->postJson('/api/products', ['name' => 'Soap', 'selling_price' => 50, 'stock_quantity' => 0])->assertForbidden();
});

it('sends cost_price as null to someone without a cost permission, and the cost to someone with it', function () {
    Sanctum::actingAs(($this->member)('cashier'), ['*']);
    $hidden = $this->getJson("/api/products/{$this->product->getRouteKey()}")->assertOk();
    expect($hidden->json('data'))->toHaveKey('cost_price')
        ->and($hidden->json('data.cost_price'))->toBeNull()
        ->and($this->getJson('/api/products')->assertOk()->json('data.data.0'))->toHaveKey('cost_price')
        ->and($this->getJson('/api/products')->json('data.data.0.cost_price'))->toBeNull();

    Sanctum::actingAs(($this->member)('manager'), ['*']);
    expect((float) $this->getJson("/api/products/{$this->product->getRouteKey()}")->assertOk()->json('data.cost_price'))->toBe(42.5);
});

it('makes a product with no cost for someone without products.set-cost, and refuses their cost change', function () {
    $member = User::factory()->create();
    $member->givePermissionTo(['products.view', 'products.create', 'products.update']);
    $member->shops()->attach($this->shop);
    Sanctum::actingAs($member, ['*']);

    $uuid = $this->postJson('/api/products', ['name' => 'Soap', 'category_id' => Category::factory()->create()->id, 'selling_price' => 50, 'cost_price' => 30, 'stock_quantity' => 0, 'status' => 'active'])
        ->assertCreated()->json('data.uuid');
    $made = Product::where('uuid', $uuid)->firstOrFail();
    expect($made->cost_price)->toBeNull()
        ->and($made->status->value)->toBe('active');

    $this->putJson("/api/products/{$this->product->getRouteKey()}", ['cost_price' => 10])
        ->assertForbidden()->assertJsonPath('message', "You can't set purchase costs.");
    expect((float) $this->product->fresh()->cost_price)->toBe(42.5);

    // The cost sent back unchanged is no change.
    $this->putJson("/api/products/{$this->product->getRouteKey()}", ['cost_price' => 42.50, 'name' => 'Renamed'])->assertOk();
});

it('refuses a product with variations on the flat API and creates nothing', function () {
    Sanctum::actingAs(($this->member)('manager'), ['*']);
    $before = Product::count();

    $this->postJson('/api/products', ['name' => 'Shirt', 'selling_price' => 50, 'stock_quantity' => 0, 'has_variations' => true])
        ->assertUnprocessable()->assertJsonPath('message', 'Create products with variations on the web.');
    $this->postJson('/api/products', ['name' => 'Shirt', 'selling_price' => 50, 'stock_quantity' => 0, 'variations' => [['name' => 'Red']]])
        ->assertUnprocessable();

    expect(Product::count())->toBe($before);
});

it('takes the cost permissions off the clerk template and every copy, keeps a custom role\'s, and the rollout doesn\'t give them back', function () {
    $business = Business::factory()->create();
    $tenant = app(TenantContext::class);
    $copy = Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', 'inventory-clerk')->firstOrFail();
    $custom = $tenant->run($business, fn () => Role::create(['name' => 'costing-clerk', 'guard_name' => 'web', 'business_id' => $business->id]));
    // As before this stage: the template and copy hold them, and an owner gave the custom role one.
    foreach ([Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'inventory-clerk')->firstOrFail(), $copy, $custom] as $role) {
        $role->givePermissionTo(['products.set-cost', 'products.view-cost']);
    }
    DB::table('role_revoked_permissions')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require database_path('migrations/2026_11_03_000002_revoke_clerk_cost_permissions.php'))->up();
    $this->artisan('permissions:sync')->assertSuccessful();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($copy->fresh()->hasPermissionTo('products.set-cost'))->toBeFalse()
        ->and($copy->fresh()->hasPermissionTo('products.view-cost'))->toBeFalse()
        ->and($custom->fresh()->hasPermissionTo('products.set-cost'))->toBeTrue()
        ->and(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->count())->toBe(2);
});

it('sends no cost field in the till\'s sync pull', function () {
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $owner = User::factory()->create();
    $owner->assignRole(ownerRole());
    $register = Register::defaultFor($this->shop->id);
    app(TenantContext::class)->forget();
    $token = Till::token($owner, $register);
    Till::freshRequest();

    $data = $this->withToken($token)->getJson('/api/v1/sync/pull')->assertOk()->json('data');
    $keys = [];
    $walk = function ($value) use (&$walk, &$keys): void {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                is_string($key) && $keys[] = $key;
                $walk($item);
            }
        }
    };
    $walk(array_intersect_key($data, array_flip(['products', 'variations', 'stock', 'staff'])));

    expect($data)->toHaveKey('products')
        ->and(array_values(array_filter(array_unique($keys), fn (string $key): bool => str_contains($key, 'cost'))))->toBe([]);
});
