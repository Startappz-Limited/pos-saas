<?php

namespace App\Support;

use App\Models\Business;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The roles every business starts with. Each business gets its own copy (a
 * Spatie "team" role with business_id set), so an admin can tailor them
 * without affecting any other business. super-admin and admin are not here:
 * they are global system roles, created by RoleSeeder.
 */
class DefaultRoles
{
    /**
     * @return array<string, array{description: string, permissions: array<int, string>}>
     */
    public static function definitions(): array
    {
        return [
            'manager' => [
                'description' => 'Manages shops, inventory, sales, and reports',
                'permissions' => [
                    // Users (view, create, update only)
                    'users.view',
                    'users.create',
                    'users.update',

                    // Roles & Permissions (view only)
                    'roles.view',
                    'permissions.view',

                    // Shops (full access)
                    'shops.view',
                    'shops.create',
                    'shops.update',
                    'shops.activate',
                    'shops.deactivate',

                    // Categories (full access)
                    'categories.view',
                    'categories.create',
                    'categories.update',
                    'categories.delete',

                    // Product attributes (full access, like categories)
                    'attributes.view',
                    'attributes.create',
                    'attributes.update',
                    'attributes.delete',

                    // Products (full access)
                    'products.view',
                    'products.create',
                    'products.update',
                    'products.delete',
                    'products.import',
                    'products.export',
                    'products.view-cost',
                    'products.set-cost',

                    // Pricing (full access)
                    'pricing.view',
                    'pricing.update',
                    'pricing.bulk-update',

                    // Suppliers (full access)
                    'suppliers.view',
                    'suppliers.create',
                    'suppliers.update',
                    'suppliers.delete',

                    // Purchase Orders (full workflow including approval)
                    'purchase_orders.view',
                    'purchase_orders.create',
                    'purchase_orders.update',
                    'purchase_orders.delete',
                    'purchase_orders.approve',

                    // Purchase Returns (full workflow)
                    'purchase_returns.view',
                    'purchase_returns.view-all',
                    'purchase_returns.create',
                    'purchase_returns.update',
                    'purchase_returns.edit-all',
                    'purchase_returns.delete',
                    'purchase_returns.delete-all',
                    'purchase_returns.approve',
                    'purchase_returns.ship',
                    'purchase_returns.complete',
                    'purchase_returns.cancel',

                    // Stock Intake (full access including approval)
                    'stock-intake.view',
                    'stock-intake.create',
                    'stock-intake.update',
                    'stock-intake.delete',
                    'stock-intake.approve',

                    // Stock Adjustments (full access including approval)
                    'stock-adjustments.view',
                    'stock-adjustments.create',
                    'stock-adjustments.update',
                    'stock-adjustments.delete',
                    'stock-adjustments.approve',

                    // Stock Movements (view only - system generated)
                    'stock-movements.view',

                    // Inventory Snapshots (full access)
                    'inventory-snapshots.view',
                    'inventory-snapshots.create',
                    'inventory-snapshots.delete',

                    // Inventory (full access)
                    'inventory.view',
                    'inventory.adjust',
                    'inventory.transfer',
                    'inventory.count',

                    // Sales (full access including cross-shop)
                    'sales.view',
                    'sales.view-all',
                    'sales.create',
                    'sales.update',
                    'sales.edit-all',
                    'sales.void',
                    'sales.refund',
                    'sales.restore',

                    // Refunds (full access including approval)
                    'refunds.view',
                    'refunds.view-all',
                    'refunds.create',
                    'refunds.approve',

                    // Returns (full access including approval)
                    'returns.view',
                    'returns.view-all',
                    'returns.create',
                    'returns.update',
                    'returns.delete',
                    'returns.approve',

                    // Ecommerce Orders (full access including cross-shop)
                    'ecommerce-orders.view',
                    'ecommerce-orders.view-all',
                    'ecommerce-orders.convert',
                    'ecommerce-orders.update',
                    'ecommerce-orders.edit-all',

                    // Abandoned Carts
                    'abandoned-carts.view',
                    'abandoned-carts.update',
                    'abandoned-carts.contact',
                    'abandoned-carts.convert',

                    // Payments (full access)
                    'payments.view',
                    'payments.create',
                    'payments.update',
                    'payments.verify',

                    // Credit Sales (full access)
                    'credit-sales.view',
                    'credit-sales.create',
                    'credit-sales.update',
                    'credit-sales.collect',
                    'credit-sales.write-off',

                    // Expenses (full access including approval)
                    'expense-categories.view',
                    'expense-categories.create',
                    'expense-categories.update',
                    'expenses.view',
                    'expenses.create',
                    'expenses.update',
                    'expenses.approve',

                    // Advertising (full access)
                    'advertising.view',
                    'advertising.create',
                    'advertising.update',
                    'advertising.delete',
                    'advertising.analyze',

                    // Alerts & Notifications (full access)
                    'alerts.view',
                    'alerts.create',
                    'alerts.update',
                    'alerts.delete',
                    'notifications.view',
                    'notifications.send',

                    // Reports (full access)
                    'reports.view',
                    'reports.sales',
                    'reports.inventory',
                    'reports.expenses',
                    'reports.profit-loss',
                    'reports.export',
                    'dashboard.view',
                    'dashboard.analytics',

                    // Audit Logs (view and export)
                    'audit-logs.view',
                    'audit-logs.export',

                    // Settings (view and update)
                    'settings.view',
                    'settings.update',
                ],
            ],
            'cashier' => [
                'description' => 'Handles sales, payments, and basic inventory tasks',
                'permissions' => [
                    // Products (view only)
                    'products.view',

                    // Inventory (view and count)
                    'inventory.view',
                    'inventory.count',

                    // Sales (full access except void and delete)
                    'sales.view',
                    'sales.create',
                    'sales.update',
                    'sales.refund',

                    // Returns (view and create)
                    'returns.view',
                    'returns.create',
                    'returns.update',

                    // Refunds (view and create)
                    'refunds.view',
                    'refunds.create',

                    // Ecommerce Orders (view and convert)
                    'ecommerce-orders.view',
                    'ecommerce-orders.convert',

                    // Abandoned Carts (follow up and sell)
                    'abandoned-carts.view',
                    'abandoned-carts.update',
                    'abandoned-carts.contact',
                    'abandoned-carts.convert',

                    // Payments (create and view)
                    'payments.view',
                    'payments.create',

                    // Credit Sales (view, create, collect)
                    'credit-sales.view',
                    'credit-sales.create',
                    'credit-sales.collect',

                    // Alerts (view only)
                    'alerts.view',

                    // Dashboard (view only)
                    'dashboard.view',

                    // Reports (basic sales reports)
                    'reports.view',
                    'reports.sales',
                ],
            ],
            'inventory-clerk' => [
                'description' => 'Manages inventory, stock intake, and product information',
                'permissions' => [
                    // Categories (full access)
                    'categories.view',
                    'categories.create',
                    'categories.update',
                    'categories.delete',

                    // Product attributes (full access, like categories)
                    'attributes.view',
                    'attributes.create',
                    'attributes.update',
                    'attributes.delete',

                    // Products (full access)
                    'products.view',
                    'products.create',
                    'products.update',
                    'products.delete',
                    'products.import',
                    'products.export',
                    'products.view-cost',
                    'products.set-cost',

                    // Suppliers (full access)
                    'suppliers.view',
                    'suppliers.create',
                    'suppliers.update',
                    'suppliers.delete',

                    // Stock Intake (full access except approval)
                    'stock-intake.view',
                    'stock-intake.create',
                    'stock-intake.update',
                    'stock-intake.delete',

                    // Stock Adjustments (view and create, no approval)
                    'stock-adjustments.view',
                    'stock-adjustments.create',
                    'stock-adjustments.update',

                    // Purchase Returns (request and prepare supplier returns)
                    'purchase_returns.view',
                    'purchase_returns.create',
                    'purchase_returns.update',

                    // Stock Movements (view only)
                    'stock-movements.view',

                    // Inventory Snapshots (view and create)
                    'inventory-snapshots.view',
                    'inventory-snapshots.create',

                    // Inventory (full access)
                    'inventory.view',
                    'inventory.adjust',
                    'inventory.transfer',
                    'inventory.count',

                    // Alerts (view and acknowledge)
                    'alerts.view',
                    'alerts.update',

                    // Dashboard (view only)
                    'dashboard.view',

                    // Reports (inventory reports)
                    'reports.view',
                    'reports.inventory',
                ],
            ],
            'accountant' => [
                'description' => 'Manages expenses, payments, and financial reports',
                'permissions' => [
                    // Sales (view only)
                    'sales.view',

                    // Payments (full access)
                    'payments.view',
                    'payments.create',
                    'payments.update',
                    'payments.verify',

                    // Credit Sales (full access)
                    'credit-sales.view',
                    'credit-sales.create',
                    'credit-sales.update',
                    'credit-sales.collect',
                    'credit-sales.write-off',

                    // Expenses (full access including approval)
                    'expense-categories.view',
                    'expense-categories.create',
                    'expense-categories.update',
                    'expense-categories.delete',
                    'expenses.view',
                    'expenses.create',
                    'expenses.update',
                    'expenses.delete',
                    'expenses.approve',

                    // Advertising (view and analyze)
                    'advertising.view',
                    'advertising.analyze',

                    // Dashboard (full analytics access)
                    'dashboard.view',
                    'dashboard.analytics',

                    // Reports (full financial reports)
                    'reports.view',
                    'reports.sales',
                    'reports.expenses',
                    'reports.profit-loss',
                    'reports.export',

                    // Audit Logs (view and export)
                    'audit-logs.view',
                    'audit-logs.export',
                ],
            ],
            'customer' => [
                'description' => 'Limited access for customer-facing features',
                'permissions' => [
                    // Products (view only)
                    'products.view',

                    // Dashboard (basic view)
                    'dashboard.view',
                ],
            ],
        ];
    }

    /**
     * Create the business's default roles it does not have yet, granting the
     * default permissions that exist. Safe to run again: it only adds.
     */
    public static function seedFor(Business $business): void
    {
        $existing = Permission::query()->pluck('name')->flip();

        foreach (self::definitions() as $name => $definition) {
            $role = Role::query()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $name, 'guard_name' => 'web'],
            );

            $role->givePermissionTo(
                array_values(array_filter($definition['permissions'], fn (string $permission): bool => $existing->has($permission)))
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
