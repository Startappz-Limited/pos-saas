<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Role;
use App\Support\Authorization\RoleRevocations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives a shared role template, and every business's copy of it
 * (`roles.template_id`), permissions they lack (hotfix fix/api-authz). Used by
 * the migrations that roll a template change out to existing businesses, which
 * `RoleSeeder` can't reach for permissions created before the copies (stage 10,
 * gap 11).
 *
 * It only adds: no other grant changes, so a permission a business took off
 * its copy stays off. A second run changes nothing. Each role that gains a
 * permission gets an audit entry (`permissions_attached`, as
 * AuditAuthorizationChanges records a grant made through the role screens),
 * with no person as the actor ("System") and the reason given.
 */
final class GrantTemplatePermissions
{
    /**
     * @param  list<string>  $permissions
     * @return array<int, list<string>> role id => the permissions it gained
     */
    public function execute(string $roleName, array $permissions, string $reason): array
    {
        $template = DB::table('roles')->whereNull('business_id')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        $ids = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id', 'name');

        // A fresh database seeds both afterwards, and the seeder grants them to the new template.
        if ($template === null || $ids->isEmpty()) {
            return [];
        }

        $roles = DB::table('roles')->where('id', $template)->orWhere('template_id', $template)->get(['id', 'uuid', 'business_id']);
        // The 2 November 2026 migrations run before `role_revoked_permissions` exists.
        $revocations = Schema::hasTable('role_revoked_permissions');
        $gained = [];

        DB::transaction(function () use ($roles, $ids, $revocations, &$gained): void {
            foreach ($roles as $role) {
                // Held, or taken off on purpose (stage 10, step 0): neither is granted.
                $held = DB::table('role_has_permissions')->where('role_id', $role->id)->whereIn('permission_id', $ids->values())
                    ->pluck('permission_id')->merge($revocations ? RoleRevocations::ids((int) $role->id) : [])->map(fn ($id): int => (int) $id)->all();
                $missing = $ids->reject(fn ($id): bool => in_array((int) $id, $held, true));

                if ($missing->isEmpty()) {
                    continue;
                }

                DB::table('role_has_permissions')->insertOrIgnore(
                    $missing->values()->map(fn ($id): array => ['permission_id' => $id, 'role_id' => $role->id])->all()
                );
                $gained[(int) $role->id] = $missing->keys()->sort()->values()->all();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($roles as $role) {
            if (! isset($gained[(int) $role->id])) {
                continue;
            }

            AuditableEvent::dispatch(
                auditableType: Role::class,
                auditableId: (int) $role->id,
                auditableUuid: $role->uuid,
                event: AuditEvent::UPDATED,
                newValues: ['permissions_attached' => $gained[(int) $role->id], 'reason' => $reason],
                tags: ['authorization', 'rollout'],
                businessId: $role->business_id === null ? null : (int) $role->business_id,
            );
        }

        return $gained;
    }
}
