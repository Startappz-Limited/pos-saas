<?php

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create permissions
    Permission::create(['name' => 'products.view']);
    Permission::create(['name' => 'products.create']);
    Permission::create(['name' => 'products.update']);
    Permission::create(['name' => 'products.delete']);

    // Assign permissions to user
    $role = Role::create(['name' => 'admin']);
    $role->givePermissionTo(['products.view', 'products.create', 'products.update', 'products.delete']);
    $this->user->assignRole($role);

    $this->category = Category::factory()->create();
    $this->shop = Shop::factory()->create();
});

// Authorization Tests
test('authorized user can view products', function () {
    $response = $this->get(route('products.index'));
    $response->assertOk();
});

test('unauthorized user cannot view products', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('products.index'));
    $response->assertForbidden();
});

test('authorized user can create product', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'Test Product',
        'sku' => 'SKU-TEST-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'name' => 'Test Product',
        'sku' => 'SKU-TEST-001',
    ]);
});

test('authorized user can assign product to shops', function () {
    $shops = Shop::factory()->count(2)->create();

    $response = $this->post(route('products.store'), [
        'name' => 'Shop Assigned Product',
        'sku' => 'SKU-SHOPS-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'cost_price' => 100,
        'selling_price' => 150,
        'sync_shops' => true,
        'shop_ids' => $shops->pluck('id')->all(),
    ]);

    $response->assertRedirect();

    $product = Product::where('sku', 'SKU-SHOPS-001')->first();

    expect($product?->shops()->pluck('shops.id')->sort()->values()->all())
        ->toBe($shops->pluck('id')->sort()->values()->all());
});

// Validation Tests
test('product name is required', function () {
    $response = $this->post(route('products.store'), [
        'sku' => 'SKU-TEST-001',
        'category_id' => $this->category->id,
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    $response->assertSessionHasErrors('name');
});

test('product SKU and cost price default when omitted', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'Test Product',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'selling_price' => 150,
    ]);

    $response->assertRedirect();

    $product = Product::where('name', 'Test Product')->first();

    expect($product)->not()->toBeNull()
        ->and($product->sku)->not()->toBeEmpty()
        ->and(str_starts_with($product->sku, 'PRD-'))->toBeTrue()
        ->and((float) $product->cost_price)->toBe(0.0);
});

test('product image upload is stored', function () {
    Storage::fake('public');

    $response = $this->post(route('products.store'), [
        'name' => 'Image Product',
        'sku' => 'SKU-IMAGE-001',
        'category_id' => $this->category->id,
        'shop_id' => $this->shop->id,
        'selling_price' => 150,
        'image' => UploadedFile::fake()->image('product.jpg'),
    ]);

    $response->assertRedirect();

    $product = Product::where('name', 'Image Product')->first();

    expect($product?->image)->not()->toBeEmpty();
    Storage::disk('public')->assertExists($product->image);
});

test('product SKU must be unique', function () {
    Product::factory()->create(['sku' => 'SKU-DUPLICATE']);

    $response = $this->post(route('products.store'), [
        'name' => 'Test Product',
        'sku' => 'SKU-DUPLICATE',
        'category_id' => $this->category->id,
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    $response->assertSessionHasErrors('sku');
});

test('category is required', function () {
    $response = $this->post(route('products.store'), [
        'name' => 'Test Product',
        'sku' => 'SKU-TEST-001',
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    $response->assertSessionHasErrors('category_id');
});

// CRUD Tests
test('can update product', function () {
    $product = Product::factory()->create(['name' => 'Original Name']);

    $response = $this->put(route('products.update', $product), [
        'name' => 'Updated Name',
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Updated Name',
    ]);
});

test('can update product shop assignments', function () {
    $oldShop = Shop::factory()->create();
    $newShop = Shop::factory()->create();
    $product = Product::factory()->create(['name' => 'Original Name']);
    $product->shops()->attach($oldShop);

    $response = $this->put(route('products.update', $product), [
        'name' => 'Updated Name',
        'sku' => $product->sku,
        'category_id' => $product->category_id,
        'cost_price' => $product->cost_price,
        'selling_price' => $product->selling_price,
        'sync_shops' => true,
        'shop_ids' => [$newShop->id],
    ]);

    $response->assertRedirect();

    expect($product->fresh()->shops()->pluck('shops.id')->all())->toBe([$newShop->id]);
});

test('can delete product', function () {
    $product = Product::factory()->create();

    $response = $this->delete(route('products.destroy', $product));

    $response->assertRedirect();
    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

test('can activate product', function () {
    $product = Product::factory()->inactive()->create();

    $response = $this->post(route('products.activate', $product));

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'status' => ProductStatus::ACTIVE->value,
    ]);
});

test('can deactivate product', function () {
    $product = Product::factory()->active()->create();

    $response = $this->post(route('products.deactivate', $product));

    $response->assertRedirect();
    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'status' => ProductStatus::INACTIVE->value,
    ]);
});

// Model Tests
test('product factory creates valid product', function () {
    $product = Product::factory()->create();

    expect($product->uuid)->not()->toBeEmpty()
        ->and($product->name)->not()->toBeEmpty()
        ->and($product->sku)->not()->toBeEmpty()
        ->and($product->status)->toBe(ProductStatus::ACTIVE);
});

test('product inactive state works', function () {
    $product = Product::factory()->inactive()->create();

    expect($product->status)->toBe(ProductStatus::INACTIVE)
        ->and($product->isActive())->toBeFalse();
});

test('product out of stock state works', function () {
    $product = Product::factory()->outOfStock()->create();

    expect($product->status)->toBe(ProductStatus::OUT_OF_STOCK)
        ->and($product->stock_quantity)->toBe(0)
        ->and($product->isOutOfStock())->toBeTrue();
});

test('product low stock state works', function () {
    $product = Product::factory()->lowStock()->create();

    expect($product->isLowStock())->toBeTrue()
        ->and($product->stock_quantity)->toBeLessThanOrEqual($product->reorder_level);
});

// Helper Method Tests
test('isActive method returns correct boolean', function () {
    $activeProduct = Product::factory()->active()->create();
    $inactiveProduct = Product::factory()->inactive()->create();

    expect($activeProduct->isActive())->toBeTrue()
        ->and($inactiveProduct->isActive())->toBeFalse();
});

test('canBeSold method checks status and stock', function () {
    $activeProduct = Product::factory()->active()->create(['stock_quantity' => 10]);
    $outOfStockProduct = Product::factory()->create(['status' => ProductStatus::ACTIVE, 'stock_quantity' => 0]);
    $inactiveProduct = Product::factory()->inactive()->create(['stock_quantity' => 10]);

    expect($activeProduct->canBeSold())->toBeTrue()
        ->and($outOfStockProduct->canBeSold())->toBeFalse()
        ->and($inactiveProduct->canBeSold())->toBeFalse();
});

test('profit margin is calculated correctly', function () {
    $product = Product::factory()->create([
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    expect(round($product->profit_margin, 2))->toBe(33.33);
});

test('profit is calculated correctly', function () {
    $product = Product::factory()->create([
        'cost_price' => 100,
        'selling_price' => 150,
    ]);

    expect($product->profit)->toBe(50.0);
});

// Scope Tests
test('active scope returns only active products', function () {
    Product::factory()->active()->count(3)->create();
    Product::factory()->inactive()->count(2)->create();

    $activeProducts = Product::active()->get();

    expect($activeProducts)->toHaveCount(3);
});

test('lowStock scope returns products below reorder level', function () {
    Product::factory()->lowStock()->count(3)->create();
    Product::factory()->create(['stock_quantity' => 50]);

    $lowStockProducts = Product::lowStock()->get();

    expect($lowStockProducts)->toHaveCount(3);
});

// Relationship Tests
test('product belongs to category', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    expect($product->category)->toBeInstanceOf(Category::class)
        ->and($product->category->id)->toBe($category->id);
});

test('product can have variations', function () {
    $product = Product::factory()->withVariations()->create();

    expect($product->has_variations)->toBeTrue();
});
