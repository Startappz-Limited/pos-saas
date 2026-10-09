---
title: Generate Tests
description: Creates comprehensive Pest tests for existing functionality with authorization and edge case coverage
---

Generate comprehensive Pest tests for:

{{target|prompt:Specify class name, file path, or feature to test}}

## Test Coverage Requirements

Generate tests for:

### 1. Happy Path

- Test successful operations with valid data
- Test all major user flows
- Verify expected outcomes

### 2. Authorization

```php
// Test unauthorized access
it('prevents unauthorized access', function () { ... });

// Test shop isolation (multi-tenant)
it('prevents access to other shop data', function () { ... });

// Test super-admin bypass
it('allows super-admin to access all data', function () { ... });
```

### 3. Validation

```php
// Test required fields
it('validates required fields', function () { ... });

// Test field formats
it('validates email format', function () { ... });

// Test unique constraints
it('prevents duplicate entries', function () { ... });
```

### 4. Edge Cases

- Test with missing/null data
- Test boundary conditions
- Test concurrent operations if applicable
- Test deletion dependencies

### 5. Database Changes

```php
// Verify records created
assertDatabaseHas('table', ['field' => 'value']);

// Verify records deleted
assertDatabaseMissing('table', ['id' => $id]);

// Verify counts
assertDatabaseCount('table', 5);
```

## Test Structure

Follow this pattern:

```php
use App\Models\User;
use App\Models\Shop;
use function Pest\Laravel\{actingAs, assertDatabaseHas};

it('descriptive test name', function () {
    // Arrange - Set up test data
    $shop = Shop::factory()->create();
    $user = User::factory()->forShop($shop)->create();

    // Act - Perform the action
    actingAs($user)->post(route(...), [...]);

    // Assert - Verify results
    assertDatabaseHas('table', [...]);
});
```

## After Generation

1. Review tests for completeness
2. Run tests: `php artisan test --compact --filter={{testClass}}`
3. Verify all tests pass
4. Check coverage if needed
