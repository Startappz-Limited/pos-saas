<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Which templates this run created (name => true), and which existed.
     *
     * @var array<string, bool>
     */
    private array $created = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create roles with descriptions
        $roles = [
            [
                // The business owner (decision 4.4); the platform operator is
                // users.is_platform_operator, not a role.
                'name' => 'admin',
                'description' => 'Owns the business: every permission in it',
            ],
            [
                'name' => 'manager',
                'description' => 'Manages shops, inventory, sales, and reports',
            ],
            [
                'name' => 'cashier',
                'description' => 'Handles sales, payments, and basic inventory tasks',
            ],
            [
                'name' => 'inventory-clerk',
                'description' => 'Manages inventory, stock intake, and product information',
            ],
            [
                'name' => 'accountant',
                'description' => 'Manages expenses, payments, and financial reports',
            ],
            [
                'name' => 'customer',
                'description' => 'Limited access for customer-facing features',
            ],
        ];

        // The seeded roles are templates shared by every business (no
        // business_id), whichever business is current (Spatie teams, stage 1a).
        app(TenantContext::class)->withoutBusiness(function () use ($roles): void {
            foreach ($roles as $roleData) {
                $role = Role::firstOrCreate(
                    ['name' => $roleData['name']],
                    ['guard_name' => 'web']
                );
                $this->created[$role->name] = $role->wasRecentlyCreated;
            }

            // Assign permissions to roles
            $this->assignSuperAdminPermissions();
            $this->assignManagerPermissions();
            $this->assignCashierPermissions();
            $this->assignInventoryClerkPermissions();
            $this->assignAccountantPermissions();
            $this->assignCustomerPermissions();
            $this->grantNewPermissionsToBusinessCopies();
        });

        $this->command->info('✅ Created '.count($roles).' roles and assigned permissions successfully!');
    }

    /**
     * Assign all permissions to Super Admin
     */
    private function assignSuperAdminPermissions(): void
    {
        $role = Role::findByName('admin');
        $this->grant($role, Permission::query()->pluck('name')->all());
    }

    /**
     * Gives a template its listed permissions. A template this run created
     * gets them all; an existing one only those created since it last
     * changed (Phase 5), as copies do: a template edited on the platform
     * console keeps the edit when the seeder runs again, and a permission
     * rolled out later still reaches it.
     *
     * @param  array<int, string>  $names
     */
    private function grant(Role $role, array $names): void
    {
        if (! ($this->created[$role->name] ?? false)) {
            $names = Permission::query()->whereIn('name', $names)
                ->where('created_at', '>', $role->updated_at)
                ->pluck('name')->all();
        }

        $role->givePermissionTo($names);
    }

    /**
     * Each business has its own copies of the templates (stage 3). A
     * permission created after a business made its copies is granted to them
     * as it is to the template, so rolling out a new permission still reaches
     * every business; permissions a business took away from its copies before
     * stay away.
     */
    private function grantNewPermissionsToBusinessCopies(): void
    {
        // Each copy names its template (roles.template_id, Phase 5).
        foreach (DB::table('roles as copies')->join('roles as templates', 'templates.id', '=', 'copies.template_id')
            ->whereNotNull('copies.business_id')->get(['copies.id', 'copies.created_at', 'copies.template_id']) as $copy) {
            $newer = DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('role_has_permissions.role_id', $copy->template_id)
                ->where('permissions.created_at', '>', $copy->created_at)
                ->pluck('permissions.id');

            DB::table('role_has_permissions')->insertOrIgnore($newer->map(fn ($id): array => ['permission_id' => $id, 'role_id' => $copy->id])->all());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Assign permissions to Manager role
     */
    private function assignManagerPermissions(): void
    {
        $role = Role::findByName('manager');

        $permissions = [
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

            // Product attributes (full access, Phase 5)
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

            // The stock menu (Phase 5)
            'stock.view',

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

            // Settings (view and update)
            'settings.view',
            'settings.update',
        ];

        $this->grant($role, $permissions);
    }

    /**
     * Assign permissions to Cashier role
     */
    private function assignCashierPermissions(): void
    {
        $role = Role::findByName('cashier');

        $permissions = [
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
        ];

        $this->grant($role, $permissions);
    }

    /**
     * Assign permissions to Inventory Clerk role
     */
    private function assignInventoryClerkPermissions(): void
    {
        $role = Role::findByName('inventory-clerk');

        $permissions = [
            // Categories (full access)
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',

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

            // The stock menu (Phase 5)
            'stock.view',

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
        ];

        $this->grant($role, $permissions);
    }

    /**
     * Assign permissions to Accountant role
     */
    private function assignAccountantPermissions(): void
    {
        $role = Role::findByName('accountant');

        $permissions = [
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

        ];

        $this->grant($role, $permissions);
    }

    /**
     * Assign permissions to Customer role
     */
    private function assignCustomerPermissions(): void
    {
        $role = Role::findByName('customer');

        $permissions = [
            // Products (view only)
            'products.view',

            // Dashboard (basic view)
            'dashboard.view',
        ];

        $this->grant($role, $permissions);
    }
}
