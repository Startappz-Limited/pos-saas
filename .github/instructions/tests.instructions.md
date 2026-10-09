---
description: Test development using Pest 4 framework with feature and unit tests. Use when writing tests, creating test files, adding assertions, testing features, or when user mentions test, spec, TDD, assertion, or expects."
applyTo: "**/tests/**"
---

# Testing Guidelines (Pest 4)

## Test Creation

```bash
# Feature test (most common)
php artisan make:test FeatureNameTest --pest

# Unit test
php artisan make:test UnitNameTest --pest --unit
```

## Feature Test Structure

```php
use App\Models\User;
use App\Models\Shop;
use function Pest\Laravel\{actingAs, assertDatabaseHas, post};

it('can create a product', function () {
    // Arrange
    $shop = Shop::factory()->create();
    $user = User::factory()->forShop($shop)->create();

    // Act
    actingAs($user)
        ->post(route('products.store'), [
            'name' => 'Test Product',
            'price' => 99.99,
            'quantity' => 10,
        ]);

    // Assert
    assertDatabaseHas('products', [
        'shop_id' => $shop->id,
        'name' => 'Test Product',
        'price' => 99.99,
    ]);
});

it('prevents unauthorized access', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('admin.dashboard'))
        ->assertForbidden();
});
```

## Common Patterns

### Using Factories

```php
// Check factory for custom states first
$user = User::factory()->withRole('admin')->create();
$product = Product::factory()->forShop($shop)->create();
```

### Testing Authorization

```php
it('super-admin can access all shops', function () {
    $superAdmin = User::factory()->withRole('super-admin')->create();
    $shop = Shop::factory()->create();

    actingAs($superAdmin)
        ->get(route('shops.show', $shop))
        ->assertOk();
});
```

### Testing Validation

```php
it('validates required fields', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->post(route('products.store'), [])
        ->assertSessionHasErrors(['name', 'price']);
});
```

### Testing Actions

```php
use App\Actions\CompleteSale;

it('completes sale and updates inventory', function () {
    $sale = Sale::factory()->pending()->create();
    $item = SaleItem::factory()->for($sale)->create(['quantity' => 5]);

    $initialQty = $item->product->quantity;

    (new CompleteSale)->execute($sale);

    expect($sale->fresh()->status)->toBe('completed')
        ->and($item->product->fresh()->quantity)->toBe($initialQty - 5);
});
```

## Assertions

```php
// Status
->assertOk()
->assertForbidden()
->assertNotFound()
->assertRedirect()

// Database
assertDatabaseHas('products', ['name' => 'Test'])
assertDatabaseMissing('products', ['id' => 999])
assertDatabaseCount('products', 5)

// Pest expectations
expect($value)->toBe('expected')
expect($array)->toHaveCount(3)
expect($model)->toBeInstanceOf(Product::class)
```

## Running Tests

```bash
# All tests
php artisan test --compact

# Specific file
php artisan test --compact tests/Feature/ProductTest.php

# Specific test
php artisan test --compact --filter=can_create_product

# With coverage
php artisan test --coverage
```

## CRITICAL Rules

1. ✅ Use factories for model creation
2. ✅ Test authorization for protected routes
3. ✅ Test super-admin bypass in policies
4. ✅ Use transactions (automatic in Pest)
5. ✅ Test validation rules
6. ❌ Don't delete tests without approval
