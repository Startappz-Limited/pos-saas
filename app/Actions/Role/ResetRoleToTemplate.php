<?php

namespace App\Actions\Role;

use App\Enums\AuditEvent;
use App\Models\Role;
use InvalidArgumentException;

/**
 * "Reset to default" (Phase 5): a business's copy of a role template takes
 * the template's current permissions, in that business only. Audited with
 * the permissions before and after.
 */
class ResetRoleToTemplate
{
    public function execute(Role $copy): void
    {
        $template = $copy->template ?? throw new InvalidArgumentException('Only a copy of a role template can be reset.');

        $before = $copy->permissions()->pluck('name')->sort()->values()->all();
        $copy->syncPermissions($template->permissions()->pluck('permissions.id')->all());
        $after = $copy->permissions()->pluck('name')->sort()->values()->all();

        $copy->dispatchAuditEvent(AuditEvent::UPDATED, ['permissions' => $before], ['permissions' => $after, 'reset_to_default' => true]);
    }
}
