<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Role;
use App\Support\Authorization\RoleRevocations;
use App\Support\Platform\PlatformAudit;
use DomainException;

/**
 * Edits a shared role template's permissions from the platform console
 * (Phase 5, B6), the only place they change. Business copies are left as
 * they are: the edit reaches new businesses (CopyRoleTemplates) and "Reset to
 * default". The owner role, which isn't copied, changes for every business's
 * owners at once. The template is touched, so RoleSeeder keeps the edit when
 * it runs again (it grants an existing template only permissions created
 * since it last changed). Audited with the permissions added and removed.
 */
class UpdateRoleTemplate
{
    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function execute(Role $template, array $permissionIds): void
    {
        if (! $template->isShared()) {
            throw new DomainException(__('Only a shared role template can be edited here.'));
        }

        $before = $template->permissions()->pluck('name');
        $beforeIds = $template->permissions()->pluck('permissions.id');
        $chosen = collect($permissionIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $template->syncPermissions($chosen->all());
        // A permission the console took off the template stays off when permissions roll out (stage 10, step 0).
        RoleRevocations::recordEdit((int) $template->id, $beforeIds, $chosen, auth()->id());
        $template->touch();
        $after = $template->permissions()->pluck('name');

        PlatformAudit::record('role_template.updated', null, [
            'template' => $template->name,
            'added' => $after->diff($before)->values()->all(),
            'removed' => $before->diff($after)->values()->all(),
        ], AuditEvent::UPDATED);
    }
}
