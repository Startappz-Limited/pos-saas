<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Cashiers may add a customer (decided 2 November 2026, hotfix fix/api-authz).
 * The flat API now checks `customers.create` on `POST /api/customers`, as the
 * web does, and a cashier who sells on credit needs the customer first.
 *
 * `RoleSeeder` lists it for the cashier template, but on an existing database
 * the seeder only grants permissions created after the role was last saved,
 * and copies only those created after the copy (stage 10, gap 11), so neither
 * the template nor any business's copy would get this old permission. This
 * migration grants it to the shared cashier template and to every business's
 * copy of it. It only adds: no other grant changes, and a second run changes
 * nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = DB::table('permissions')->where('name', 'customers.create')->where('guard_name', 'web')->value('id');
        $template = DB::table('roles')->whereNull('business_id')->where('name', 'cashier')->where('guard_name', 'web')->value('id');

        // A fresh database seeds both afterwards, and the seeder grants it to the new template.
        if ($permission === null || $template === null) {
            return;
        }

        $roles = DB::table('roles')->where('template_id', $template)->pluck('id')->push($template);

        DB::table('role_has_permissions')->insertOrIgnore(
            $roles->map(fn ($roleId): array => ['permission_id' => $permission, 'role_id' => $roleId])->all()
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Nothing is taken back: a business may have relied on it since.
    }
};
