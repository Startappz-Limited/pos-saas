<?php

namespace App\Actions\Role;

use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Switches a role on or off (Phase 5). An inactive role grants nothing; the
 * people holding it keep the assignment, so switching it back on restores
 * their access. Audited as a change to the role (Auditable).
 */
class SetRoleActive
{
    public function execute(Role $role, bool $active): void
    {
        if ($role->is_active === $active) {
            return;
        }

        $role->update(['is_active' => $active]);

        // Permission checks read Spatie's cache, which holds the flag.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
