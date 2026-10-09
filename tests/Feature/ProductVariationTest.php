<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    Permission::create(['name' => 'products.view']);
    Permission::create(['name' => 'products.create']);
    Permission::create(['name' => 'products.update']);
    Permission::create(['name' => 'products.delete']);

    $role = Role::create(['name' => 'admin']);
    $role->givePermissionTo(['products.view', 'products.create', 'products.update', 'products.delete']);
    $this->user->assignRole($role);

    $this->category = Category::factory()->create();
    $this->shop = Shop::factory()->create();
    $this->user->shops()->sync([$this->shop->id]);
});

// Wholesale Price Tests
test('can create product with wholesale price', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'Wholesale Product',
        'sku' => 'SKU-WS-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 100,
        'selling_price' => 200,
        'wholesale_price' => 150,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'name' => 'Wholesale Product',
        'wholesale_price' => 150,
    ]);
});

test('can create product without a wholesale price', function () {
    // The form and both FormRequests treat wholesale price as nullable, so an
    // empty field arrives as NULL. The column used to be NOT NULL, which blew
    // up the insert with "Column 'wholesale_price' cannot be null".
    $response = $this->post(route('products.store'), [
        'name' => 'No Wholesale Product',
        'sku' => 'SKU-WS-002',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 100,
        'selling_price' => 200,
        'wholesale_price' => null,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('products', [
        'name' => 'No Wholesale Product',
        'wholesale_price' => null,
    ]);
});

test('can clear an existing wholesale price', function () {
    $product = Product::factory()->create(['wholesale_price' => 100]);

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'wholesale_price' => null,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    expect($product->fresh()->wholesale_price)->toBeNull();
});

test('can update product wholesale price', function () {
    $product = Product::factory()->create(['wholesale_price' => 100]);

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'wholesale_price' => 175,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'wholesale_price' => 175,
    ]);
});

test('wholesale price is optional', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'No Wholesale Product',
        'sku' => 'SKU-NWS-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 100,
        'selling_price' => 200,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'name' => 'No Wholesale Product',
    ]);
});

// Variation Creation Tests
test('can create product with variations', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'T-Shirt',
        'sku' => 'SKU-TS-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 50,
        'selling_price' => 100,
        'has_variations' => true,
        'variations' => [
            [
                'name' => 'Small',
                'sku' => 'SKU-TS-001-S',
                'cost_price' => 45,
                'selling_price' => 90,
                'wholesale_price' => 70,
                'stock_quantity' => 20,
            ],
            [
                'name' => 'Large',
                'sku' => 'SKU-TS-001-L',
                'cost_price' => 55,
                'selling_price' => 110,
                'wholesale_price' => 85,
                'stock_quantity' => 15,
            ],
        ],
    ]);

    $response->assertRedirect();

    $product = Product::where('sku', 'SKU-TS-001')->first();
    expect($product->has_variations)->toBeTrue();
    expect($product->variations)->toHaveCount(2);

    $this->assertDatabaseHas('product_variations', [
        'product_id' => $product->id,
        'name' => 'Small',
        'sku' => 'SKU-TS-001-S',
        'selling_price' => 90,
        'wholesale_price' => 70,
    ]);

    $this->assertDatabaseHas('product_variations', [
        'product_id' => $product->id,
        'name' => 'Large',
        'sku' => 'SKU-TS-001-L',
        'selling_price' => 110,
        'wholesale_price' => 85,
    ]);
});

test('product without has_variations flag does not create variations', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'Simple Product',
        'sku' => 'SKU-SIMPLE-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 50,
        'selling_price' => 100,
        'variations' => [
            [
                'name' => 'Should Not Exist',
                'sku' => 'SKU-GHOST',
                'selling_price' => 90,
            ],
        ],
    ]);

    $response->assertRedirect();

    $product = Product::where('sku', 'SKU-SIMPLE-001')->first();
    expect($product->has_variations)->toBeFalse();
    expect($product->variations)->toHaveCount(0);
});

// Variation Update Tests
test('can add variations to existing product', function () {
    $product = Product::factory()->create();

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'has_variations' => true,
        'variations' => [
            [
                'name' => 'Red',
                'sku' => 'SKU-RED-001',
                'cost_price' => 40,
                'selling_price' => 80,
                'stock_quantity' => 10,
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($product->fresh()->variations)->toHaveCount(1);

    $this->assertDatabaseHas('product_variations', [
        'product_id' => $product->id,
        'name' => 'Red',
    ]);
});

test('can update existing variation', function () {
    $product = Product::factory()->withVariations()->create();
    $variation = ProductVariation::factory()->create([
        'product_id' => $product->id,
        'name' => 'Old Name',
        'sku' => 'SKU-OLD',
        'selling_price' => 50,
    ]);

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'has_variations' => true,
        'variations' => [
            [
                'id' => $variation->id,
                'name' => 'Updated Name',
                'sku' => 'SKU-UPDATED',
                'selling_price' => 75,
                'wholesale_price' => 60,
                'stock_quantity' => 25,
            ],
        ],
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('product_variations', [
        'id' => $variation->id,
        'name' => 'Updated Name',
        'sku' => 'SKU-UPDATED',
        'selling_price' => 75,
        'wholesale_price' => 60,
    ]);
});

test('removing a variation from the form deletes it', function () {
    $product = Product::factory()->withVariations()->create();
    $variationToKeep = ProductVariation::factory()->create([
        'product_id' => $product->id,
        'name' => 'Keep Me',
        'sku' => 'SKU-KEEP',
        'selling_price' => 50,
    ]);
    $variationToRemove = ProductVariation::factory()->create([
        'product_id' => $product->id,
        'name' => 'Remove Me',
        'sku' => 'SKU-REMOVE',
        'selling_price' => 60,
    ]);

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'has_variations' => true,
        'variations' => [
            [
                'id' => $variationToKeep->id,
                'name' => 'Keep Me',
                'sku' => 'SKU-KEEP',
                'selling_price' => 50,
            ],
        ],
    ]);

    $response->assertRedirect();
    expect($product->fresh()->variations)->toHaveCount(1);

    $this->assertDatabaseHas('product_variations', ['id' => $variationToKeep->id]);
    $this->assertDatabaseMissing('product_variations', ['id' => $variationToRemove->id]);
});

test('disabling has_variations deletes all variations', function () {
    $product = Product::factory()->withVariations()->create();
    ProductVariation::factory()->count(3)->create(['product_id' => $product->id]);

    expect($product->variations)->toHaveCount(3);

    $response = $this->put(route('products.update', $product), [
        'name' => $product->name,
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'has_variations' => false,
    ]);

    $response->assertRedirect();
    expect($product->fresh()->variations)->toHaveCount(0);
});

// View Tests
test('create page displays wholesale price and variations section', function () {
    $response = $this->get(route('products.create'));

    $response->assertSuccessful();
    $response->assertSee('Wholesale Price');
    $response->assertSee('Product Variations');
    $response->assertSee('Has Variations');
    $response->assertSee('Add Variation');
});

test('edit page displays wholesale price and existing variations', function () {
    $product = Product::factory()->withVariations()->create(['wholesale_price' => 150]);
    $variation = ProductVariation::factory()->create([
        'product_id' => $product->id,
        'name' => 'Test Variation',
        'sku' => 'SKU-TV-001',
        'selling_price' => 90,
    ]);

    $response = $this->get(route('products.edit', $product));

    $response->assertSuccessful();
    $response->assertSee('Wholesale Price');
    $response->assertSee('Product Variations');
    $response->assertSee('Test Variation');
    $response->assertSee('SKU-TV-001');
});
