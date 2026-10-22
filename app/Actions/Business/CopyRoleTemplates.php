<?php

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives a business its own copy of each shared role template (manager,
 * cashier, inventory clerk, accountant, ...) with the template's permissions,
 * so the business can change its roles without touching another business's
 * (decision 4.4). The owner role, `admin`, stays shared: owners always hold
 * every permission.
 *
 * Each copy points at its template (`template_id`, Phase 5), which "Reset to
 * default" and later permission rollouts follow.
 *
 * Idempotent: a role the business already has is left as it is.
 */
class CopyRoleTemplates
{
    public const OWNER_ROLE = 'admin';

    public function execute(Business $business): void
    {
        $templates = DB::table('roles')->whereNull('business_id')->where('name', '!=', self::OWNER_ROLE)->get();

        foreach ($templates as $template) {
            $exists = DB::table('roles')->where('business_id', $business->id)
                ->where('name', $template->name)->where('guard_name', $template->guard_name)->exists();

            if ($exists) {
                continue;
            }

            $copyId = DB::table('roles')->insertGetId([
                'business_id' => $business->id,
                'template_id' => $template->id,
                'uuid' => (string) Str::uuid(),
                'name' => $template->name,
                'guard_name' => $template->guard_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('role_has_permissions')->insert(
                DB::table('role_has_permissions')->where('role_id', $template->id)->pluck('permission_id')
                    ->map(fn ($permissionId): array => ['permission_id' => $permissionId, 'role_id' => $copyId])->all()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
