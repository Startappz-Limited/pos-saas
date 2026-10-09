<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'products.view-cost',
        'products.set-cost',
    ] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->category = Category::factory()->create();
    $this->shop = Shop::factory()->create();

    $this->userWith = function (array $permissions) {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'role-'.$user->id]);
        $role->givePermissionTo($permissions);
        $user->assignRole($role);
        $user->shops()->sync([$this->shop->id]);

        return $user;
    };

    $this->baseProductPayload = fn (array $overrides = []) => array_merge([
        'name' => 'Guarded Product',
        'sku' => 'SKU-COST-'.fake()->unique()->numerify('####'),
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'selling_price' => 200,
    ], $overrides);
});

test('a user without cost permission does not see cost on the product list', function () {
    Product::factory()->create(['cost_price' => 111.11, 'selling_price' => 200]);

    $this->actingAs(($this->userWith)(['products.view']))
        ->get(route('products.index'))
        ->assertOk()
        ->assertDontSee('111.11');
});

test('a user with view-cost permission sees cost on the product list', function () {
    Product::factory()->create(['cost_price' => 111.11, 'selling_price' => 200]);

    $this->actingAs(($this->userWith)(['products.view', 'products.view-cost']))
        ->get(route('products.index'))
        ->assertOk()
        ->assertSee('111.11');
});

test('a user without cost permission does not see cost on the product detail page', function () {
    $product = Product::factory()->create(['cost_price' => 111.11, 'selling_price' => 200]);

    $this->actingAs(($this->userWith)(['products.view']))
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertDontSee('Cost Price:')
        ->assertDontSee('111.11');
});

test('the create form only offers a cost input to a user who may set cost', function () {
    $this->actingAs(($this->userWith)(['products.view', 'products.create']))
        ->get(route('products.create'))
        ->assertOk()
        ->assertDontSee('name="cost_price"', false);

    $this->actingAs(($this->userWith)(['products.view', 'products.create', 'products.set-cost']))
        ->get(route('products.create'))
        ->assertOk()
        ->assertSee('name="cost_price"', false);
});

test('the edit form shows cost read-only to a viewer and editable to a setter', function () {
    $product = Product::factory()->create(['cost_price' => 111.11]);

    $this->actingAs(($this->userWith)(['products.view', 'products.update', 'products.view-cost']))
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('id="cost_price"', false)
        ->assertSee('111.11')
        ->assertDontSee('name="cost_price"', false);

    $this->actingAs(($this->userWith)(['products.view', 'products.update', 'products.set-cost']))
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertSee('name="cost_price"', false);
});

test('a posted cost price is ignored when the user may not set cost', function () {
    $this->actingAs(($this->userWith)(['products.view', 'products.create']))
        ->post(route('products.store'), ($this->baseProductPayload)([
            'name' => 'Smuggled Cost',
            'cost_price' => 999,
        ]))
        ->assertRedirect();

    expect(Product::where('name', 'Smuggled Cost')->first()->cost_price)->toBeNull();
});

test('a posted cost price is stored when the user may set cost', function () {
    $this->actingAs(($this->userWith)(['products.view', 'products.create', 'products.set-cost']))
        ->post(route('products.store'), ($this->baseProductPayload)([
            'name' => 'Legitimate Cost',
            'cost_price' => 120,
        ]))
        ->assertRedirect();

    expect((float) Product::where('name', 'Legitimate Cost')->first()->cost_price)->toBe(120.0);
});

test('an update by a user without set-cost leaves the existing cost untouched', function () {
    $product = Product::factory()->create(['cost_price' => 80, 'selling_price' => 200]);

    $this->actingAs(($this->userWith)(['products.view', 'products.update']))
        ->put(route('products.update', $product), [
            'name' => 'Renamed',
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'selling_price' => 250,
            'cost_price' => 5,
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->name)->toBe('Renamed')
        ->and((float) $product->selling_price)->toBe(250.0)
        ->and((float) $product->cost_price)->toBe(80.0);
});

test('an existing variation keeps its cost when edited by a user without set-cost', function () {
    $product = Product::factory()->create(['has_variations' => true]);
    $variation = $product->variations()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Large',
        'sku' => 'VAR-COST-1',
        'attributes' => [],
        'cost_price' => 40,
        'selling_price' => 100,
        'stock_quantity' => 5,
    ]);

    $this->actingAs(($this->userWith)(['products.view', 'products.update']))
        ->put(route('products.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'selling_price' => $product->selling_price,
            'has_variations' => 1,
            'variations' => [
                [
                    'id' => $variation->id,
                    'name' => 'Large',
                    'sku' => 'VAR-COST-1',
                    'selling_price' => 130,
                    'cost_price' => 1,
                ],
            ],
        ])
        ->assertRedirect();

    $variation->refresh();
    expect((float) $variation->selling_price)->toBe(130.0)
        ->and((float) $variation->cost_price)->toBe(40.0);
});

test('setting cost implies being able to view it', function () {
    $user = ($this->userWith)(['products.view', 'products.set-cost']);

    expect($user->can('viewCost', Product::class))->toBeTrue();
});

test('a super admin retains full cost access', function () {
    $user = User::factory()->create();
    $role = Role::create(['name' => 'super-admin']);
    $user->assignRole($role);

    expect($user->can('viewCost', Product::class))->toBeTrue()
        ->and($user->can('setCost', Product::class))->toBeTrue();
});
