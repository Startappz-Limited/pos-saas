---
description: Policy class development with authorization rules and permission-based access. Use when creating Policy classes, implementing authorization gates, working with user permissions, adding access control checks, or when user mentions policy, authorization, gate, or ability."
applyTo: "**/Policies/**"
---

# Policy Development Guidelines

## Core Principles

**NEVER hardcode role names in policies.** Use permission-based checks instead.

### Use the `before()` Method for Full Access

Every policy should use the `before()` method with a `full-access` permission check:

```php
public function before(User $user, string $ability): ?bool
{
    if ($user->can('module.full-access')) {
        return true;
    }

    return null;
}
```

### Permission Naming Convention

- `module.view` - View resources
- `module.view-all` - View resources across all shops
- `module.create` - Create resources
- `module.update` - Update resources
- `module.edit-all` - Edit resources across all shops
- `module.delete` - Delete resources
- `module.delete-all` - Delete resources across all shops
- `module.approve` - Approve pending resources
- `module.full-access` - Bypass all policy checks (for super-admin role)

## Policy Structure

```php
class ProductPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('products.full-access')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        // User with view-all permission can view any product
        if ($user->can('products.view-all')) {
            return true;
        }

        return $user->can('products.view') && $product->shop_id === $user->shop_id;
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        // User with edit-all permission can update any product
        if ($user->can('products.edit-all')) {
            return true;
        }

        return $user->can('products.update') && $product->shop_id === $user->shop_id;
    }

    public function delete(User $user, Product $product): bool
    {
        // User with delete-all permission can delete any product
        if ($user->can('products.delete-all')) {
            return true;
        }

        return $user->can('products.delete') && $product->shop_id === $user->shop_id;
    }
}
```

## Cross-Shop Access

Use `-all` suffixed permissions for cross-shop access:

```php
// ❌ Wrong - hardcoded role check
if ($user->hasAnyRole(['admin', 'manager'])) {
    return true;
}

// ✅ Correct - permission-based check
if ($user->can('module.view-all')) {
    return true;
}
```

## Shop Scoping

Always check shop scope for regular users:

```php
public function view(User $user, Sale $sale): bool
{
    // Cross-shop access via permission
    if ($user->can('sales.view-all')) {
        return true;
    }

    // Shop-scoped access
    return $user->can('sales.view') && $user->shop_id === $sale->shop_id;
}
```

## Adding New Permissions

When adding new permissions:

1. Add to `PermissionSeeder.php`
2. Assign to appropriate roles in `RoleSeeder.php`
3. Run `php artisan db:seed --class=PermissionSeeder`
4. Run `php artisan db:seed --class=RoleSeeder`

## Testing Policies

Always test:

1. Users with `full-access` permission can perform ALL actions
2. Users with `*-all` permissions can access cross-shop resources
3. Regular users respect shop scope restrictions
4. Permission checks work correctly for each action
