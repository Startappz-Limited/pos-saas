# Authorization & Permissions Guide

This document explains how authorization works in this application using permission-based policies.

## Overview

Authorization is handled through Laravel Policies with Spatie Permission package. We use **permission-based** access control — never hardcode role names in policies.

## Permission Naming Convention

| Permission Type | Pattern | Description |
|----------------|---------|-------------|
| View | `module.view` | View resources in own shop |
| View All | `module.view-all` | View resources across all shops |
| Create | `module.create` | Create new resources |
| Update | `module.update` | Update resources in own shop |
| Edit All | `module.edit-all` | Edit resources across all shops |
| Delete | `module.delete` | Delete resources in own shop |
| Delete All | `module.delete-all` | Delete resources across all shops |
| Approve | `module.approve` | Approve pending resources |
| Full Access | `module.full-access` | Bypass all policy checks |

## Policy Structure

### The `before()` Method

Every policy should use the `before()` method to check for full access:

```php
public function before(User $user, string $ability): ?bool
{
    if ($user->can('module.full-access')) {
        return true;
    }

    return null; // Continue to specific method
}
```

### Basic Permission Check

```php
public function create(User $user): bool
{
    return $user->can('products.create');
}
```

### Cross-Shop Access

Use `-all` suffixed permissions for cross-shop access:

```php
public function view(User $user, Sale $sale): bool
{
    // Cross-shop access
    if ($user->can('sales.view-all')) {
        return true;
    }

    // Shop-scoped access
    return $user->can('sales.view') && $user->shop_id === $sale->shop_id;
}
```

## Adding New Permissions

### 1. Add to PermissionSeeder

```php
// database/seeders/PermissionSeeder.php
$permissions = [
    // ... existing permissions
    
    // New Module
    'new-module.view',
    'new-module.view-all',
    'new-module.create',
    'new-module.update',
    'new-module.edit-all',
    'new-module.delete',
    'new-module.delete-all',
    'new-module.full-access',
];
```

### 2. Assign to Roles in RoleSeeder

```php
// database/seeders/RoleSeeder.php

// Manager gets cross-shop access
$managerPermissions = [
    'new-module.view',
    'new-module.view-all',
    'new-module.create',
    'new-module.update',
    'new-module.edit-all',
];

// Cashier gets basic access
$cashierPermissions = [
    'new-module.view',
    'new-module.create',
];
```

### 3. Run Seeders

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

## Role Capabilities

### Super Admin
- Receives ALL permissions automatically
- Has `*.full-access` permissions for all modules

### Manager
- Cross-shop access via `*.view-all` and `*.edit-all` permissions
- Can approve refunds, returns, stock adjustments
- Full access to reports and dashboard analytics

### Cashier
- Shop-scoped access only
- Can create sales, returns, refunds (cannot approve)
- Limited to basic reports

### Inventory Clerk
- Shop-scoped access
- Full inventory and stock management
- Cannot access sales or financial data

### Accountant
- Shop-scoped access
- Full expense management including approval
- Access to financial reports

## Common Patterns

### Conditional Actions (e.g., Pending Status)

```php
public function approve(User $user, StockAdjustment $adjustment): bool
{
    return $user->can('stock-adjustments.approve') 
        && $adjustment->canBeApproved();
}
```

### Immutable Resources

```php
public function update(User $user, StockMovement $movement): bool
{
    return false; // Stock movements are immutable
}
```

### Self-Protection

```php
public function delete(User $user, User $model): bool
{
    // Cannot delete yourself
    if ($user->id === $model->id) {
        return false;
    }

    return $user->can('users.delete');
}
```

## DO NOT

❌ **Never hardcode role names in policies:**

```php
// WRONG
if ($user->hasRole('super-admin')) {
    return true;
}

// WRONG
if ($user->hasAnyRole(['admin', 'manager'])) {
    return true;
}
```

✅ **Use permissions instead:**

```php
// CORRECT
if ($user->can('module.full-access')) {
    return true;
}

// CORRECT
if ($user->can('module.edit-all')) {
    return true;
}
```

## Managing Permissions via UI

Permissions can be managed through the admin interface at `/roles`. Assign or remove permissions from roles without code changes.

## Troubleshooting

### 403 Unauthorized Error

1. Check if user has the required permission
2. Verify permission exists in database
3. Clear permission cache: `php artisan permission:cache-reset`
4. Check policy method logic

### Permission Not Working

1. Ensure permission is seeded
2. Ensure permission is assigned to user's role
3. Clear all caches: `php artisan optimize:clear`
