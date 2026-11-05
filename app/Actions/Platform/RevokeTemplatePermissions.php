<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Role;
use App\Support\Authorization\RoleRevocations;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Takes permissions off a shared role template and every business's copy of it
 * (`roles.template_id`), recording each removal in `role_revoked_permissions`
 * so the rollout (`RoleSeeder`, `permissions:sync`) never gives it back (stage
 * 10, step 0). Used by the migrations that change a template's defaults: the
 * inventory clerk's cost permissions (step 1f), the manager's `sales.void`
 * (step 2).
 *
 * Custom roles a business made itself (no template) keep whatever the owner gave
 * them. Each role that loses a permission gets an audit entry
 * (`permissions_detached`, as AuditAuthorizationChanges records it), with no
 * person as the actor ("System") and the reason given. A second run changes
 * nothing.
 */
final class RevokeTemplatePermissions
{
    /**
     * @param  list<string>  $permissions
     * @param  list<int>  $keepRoleIds  roles left as they are (an owner granted the permission on purpose)
     * @return array{before: int, after: int, roles: array<int, list<string>>} grants held before and after (by the roles changed), and what each role lost
     */
    public function execute(string $roleName, array $permissions, string $reason, array $keepRoleIds = []): array
    {
        $template = DB::table('roles')->whereNull('business_id')->where('name', $roleName)->where('guard_name', 'web')->value('id');
        $ids = DB::table('permissions')->whereIn('name', $permissions)->where('guard_name', 'web')->pluck('id', 'name');

        if ($template === null || $ids->isEmpty()) {
            return ['before' => 0, 'after' => 0, 'roles' => []];
        }

        // $keepRoleIds: copies whose owner granted it on purpose keep it (stage 10, step 2).
        $roles = DB::table('roles')->where(fn ($query) => $query->where('id', $template)->orWhere('template_id', $template))
            ->whereNotIn('id', $keepRoleIds)->get(['id', 'uuid', 'business_id']);
        $held = fn (): int => DB::table('role_has_permissions')->whereIn('role_id', $roles->pluck('id'))->whereIn('permission_id', $ids->values())->count();
        $before = $held();
        $lost = [];

        DB::transaction(function () use ($roles, $ids, &$lost): void {
            foreach ($roles as $role) {
                $had = DB::table('role_has_permissions')->where('role_id', $role->id)->whereIn('permission_id', $ids->values())
                    ->pluck('permission_id')->map(fn ($id): int => (int) $id)->all();

                DB::table('role_has_permissions')->where('role_id', $role->id)->whereIn('permission_id', $ids->values())->delete();
                // Recorded even when the role no longer held it, so a later rollout doesn't add it.
                RoleRevocations::change((int) $role->id, $ids->values());

                if ($had !== []) {
                    $lost[(int) $role->id] = $ids->filter(fn ($id): bool => in_array((int) $id, $had, true))->keys()->sort()->values()->all();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($roles as $role) {
            if (! isset($lost[(int) $role->id])) {
                continue;
            }

            AuditableEvent::dispatch(
                auditableType: Role::class,
                auditableId: (int) $role->id,
                auditableUuid: $role->uuid,
                event: AuditEvent::UPDATED,
                newValues: ['permissions_detached' => $lost[(int) $role->id], 'reason' => $reason],
                tags: ['authorization', 'rollout'],
                businessId: $role->business_id === null ? null : (int) $role->business_id,
            );
        }

        return ['before' => $before, 'after' => $held(), 'roles' => $lost];
    }
}
