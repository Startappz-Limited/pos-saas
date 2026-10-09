<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all system permissions grouped by module
        $permissions = [
            // Users Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.activate',
            'users.deactivate',
            'users.suspend',
            'users.full-access',

            // Roles & Permissions Management
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.assign-permissions',
            'roles.full-access',
            'permissions.view',

            // Shops Management
            'shops.view',
            'shops.create',
            'shops.update',
            'shops.delete',
            'shops.activate',
            'shops.deactivate',
            'shops.full-access',

            // Categories Management
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'categories.full-access',

            // Products Management
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'products.import',
            'products.export',
            'products.view-cost',
            'products.set-cost',
            'products.full-access',

            // Pricing Management
            'pricing.view',
            'pricing.update',
            'pricing.bulk-update',
            'pricing.full-access',

            // Suppliers Management
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'suppliers.delete',
            'suppliers.full-access',

            // Purchase Orders
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.update',
            'purchase_orders.delete',
            'purchase_orders.approve',
            'purchase_orders.full-access',

            // Purchase Returns
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
            'purchase_returns.full-access',

            // Customers Management
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.activate',
            'customers.deactivate',
            'customers.manage-credit',
            'customers.full-access',

            // Stock Intake
            'stock-intake.view',
            'stock-intake.create',
            'stock-intake.update',
            'stock-intake.delete',
            'stock-intake.approve',
            'stock-intake.full-access',

            // Stock Adjustments
            'stock-adjustments.view',
            'stock-adjustments.create',
            'stock-adjustments.update',
            'stock-adjustments.delete',
            'stock-adjustments.approve',
            'stock-adjustments.full-access',

            // Stock Movements
            'stock-movements.view',
            'stock-movements.full-access',

            // Inventory Snapshots
            'inventory-snapshots.view',
            'inventory-snapshots.create',
            'inventory-snapshots.delete',
            'inventory-snapshots.full-access',

            // Inventory Management
            'inventory.view',
            'inventory.adjust',
            'inventory.transfer',
            'inventory.count',
            'inventory.full-access',

            // Sales Management
            'sales.view',
            'sales.view-all',
            'sales.create',
            'sales.update',
            'sales.edit-all',
            'sales.delete',
            'sales.delete-all',
            'sales.void',
            'sales.refund',
            'sales.restore',
            'sales.full-access',

            // Refunds Management
            'refunds.view',
            'refunds.view-all',
            'refunds.create',
            'refunds.approve',
            'refunds.full-access',

            // Returns Management
            'returns.view',
            'returns.view-all',
            'returns.create',
            'returns.update',
            'returns.delete',
            'returns.approve',
            'returns.full-access',

            // Ecommerce Orders
            'ecommerce-orders.view',
            'ecommerce-orders.view-all',
            'ecommerce-orders.convert',
            'ecommerce-orders.update',
            'ecommerce-orders.edit-all',
            'ecommerce-orders.full-access',

            // Abandoned Carts (website carts from the Abandoned Cart Recovery plugin)
            'abandoned-carts.view',
            'abandoned-carts.update',
            'abandoned-carts.contact',
            'abandoned-carts.convert',
            'abandoned-carts.full-access',

            // Payments Management
            'payments.view',
            'payments.create',
            'payments.update',
            'payments.delete',
            'payments.verify',
            'payments.full-access',

            // Credit Sales Management
            'credit-sales.view',
            'credit-sales.create',
            'credit-sales.update',
            'credit-sales.collect',
            'credit-sales.write-off',
            'credit-sales.full-access',

            // Expenses Management
            'expense-categories.view',
            'expense-categories.create',
            'expense-categories.update',
            'expense-categories.delete',
            'expense-categories.full-access',
            'expenses.view',
            'expenses.view-all',
            'expenses.create',
            'expenses.update',
            'expenses.edit-all',
            'expenses.delete',
            'expenses.delete-all',
            'expenses.approve',
            'expenses.full-access',

            // Advertising ROI
            'advertising.view',
            'advertising.create',
            'advertising.update',
            'advertising.delete',
            'advertising.analyze',
            'advertising.full-access',

            // Campaigns Management (social, AI, ads)
            'campaigns.view',
            'campaigns.create',
            'campaigns.update',
            'campaigns.delete',
            'campaigns.publish',
            'campaigns.full-access',

            // Social Accounts (FB, IG, Meta Ads, Google Ads)
            'social-accounts.view',
            'social-accounts.connect',
            'social-accounts.disconnect',
            'social-accounts.full-access',

            // Baileys (unofficial WhatsApp via @whiskeysockets/baileys bridge)
            'baileys.view',
            'baileys.manage',     // create/disconnect sessions
            'baileys.send',       // send messages, post to groups/channels, status
            'baileys.full-access',

            // Audit Trail (read-only — the trail is never mutable)
            'audit.view',
            'audit.export',
            'audit.full-access',

            // Alerts & Notifications
            'alerts.view',
            'alerts.create',
            'alerts.update',
            'alerts.delete',
            'alerts.full-access',
            'notifications.view',
            'notifications.send',
            'notifications.full-access',

            // Reports & Dashboard
            'reports.view',
            'reports.sales',
            'reports.inventory',
            'reports.expenses',
            'reports.profit-loss',
            'reports.export',
            'reports.full-access',
            'dashboard.view',
            'dashboard.analytics',
            'dashboard.full-access',

            // Audit Logs
            'audit-logs.view',
            'audit-logs.export',
            'audit-logs.full-access',

            // System Settings
            'settings.view',
            'settings.update',
            'settings.backup',
            'settings.restore',
            'settings.full-access',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission],
                ['guard_name' => 'web']
            );
        }

        $this->command->info('✅ Created '.count($permissions).' permissions successfully!');
    }
}
