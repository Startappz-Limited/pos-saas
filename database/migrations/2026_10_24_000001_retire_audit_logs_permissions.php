<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * One audit permission family (fix/notices-and-sidebar-ui). The audit log
 * checks `audit.view` and `audit.export`; `audit-logs.view`, `.export` and
 * `.full-access` were seeded and given to managers and accountants but never
 * checked: they granted nothing, yet looked like access in the role editor,
 * and an owner ticking them to let someone in got nothing.
 *
 * Only owners see the audit log by default; an owner may give a role in their
 * business the audit permissions. Carrying the grants across:
 * - on the shared templates and businesses' copies of them, the old grants
 *   are dropped: they gave no access, and the default stays owners only;
 * - on a custom role (an owner's own) or a person, the old grant becomes the
 *   one that works (full access: both), which is what the owner meant;
 * - then the three old permissions are removed.
 */
return new class extends Migration
{
    /** Old permission => the permissions that do what it said. */
    private const MAP = [
        'audit-logs.view' => ['audit.view'],
        'audit-logs.export' => ['audit.export'],
        'audit-logs.full-access' => ['audit.view', 'audit.export'],
    ];

    public function up(): void
    {
        $old = DB::table('permissions')->whereIn('name', array_keys(self::MAP))->where('guard_name', 'web')->pluck('id', 'name');

        if ($old->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($old): void {
            $new = $this->ensureNewPermissions();

            foreach ($old as $name => $oldId) {
                $targets = collect(self::MAP[$name])->map(fn (string $permission): int => $new[$permission]);

                // Custom roles: an owner's own choice, kept as the working permission.
                $customRoles = DB::table('role_has_permissions')
                    ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
                    ->where('role_has_permissions.permission_id', $oldId)
                    ->whereNotNull('roles.business_id')->whereNull('roles.template_id')
                    ->pluck('roles.id');

                foreach ($customRoles as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore($targets->map(fn (int $id): array => ['permission_id' => $id, 'role_id' => $roleId])->all());
                }

                // People given it directly, likewise.
                foreach (DB::table('model_has_permissions')->where('permission_id', $oldId)->get() as $grant) {
                    foreach ($targets as $id) {
                        DB::table('model_has_permissions')->insertOrIgnore([...((array) $grant), 'permission_id' => $id]);
                    }
                }
            }

            // Templates and copies lose the old grants with the permissions.
            DB::table('role_has_permissions')->whereIn('permission_id', $old->values())->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $old->values())->delete();
            DB::table('permissions')->whereIn('id', $old->values())->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The old permissions come back, without grants: they never gave
        // access. Converted grants stay, since they are what owners meant.
        foreach (array_keys(self::MAP) as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The working audit permissions' ids, created if this database has none yet.
     *
     * @return array<string, int>
     */
    private function ensureNewPermissions(): array
    {
        $ids = [];

        foreach (['audit.view', 'audit.export'] as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
            $ids[$name] = (int) DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
        }

        return $ids;
    }
};
