<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Allow users with full access to bypass all checks.
     *
     * Roles are per business. Another business's roles are off limits, and
     * global roles (super-admin, admin) are shared by every business, so only
     * a super-admin may change them; a super-admin passes Gate::before and
     * never reaches this method. Both checks run before the full-access
     * bypass, because the admin role holds roles.full-access.
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        $target = $arguments[0] ?? null;

        if (! $target instanceof Role) {
            return $user->can('roles.full-access') ? true : null;
        }

        if (! $target->isGlobal() && (int) $target->business_id !== $user->currentBusinessId()) {
            return false;
        }

        if ($target->isGlobal() && in_array($ability, ['update', 'delete', 'restore', 'forceDelete'], true)) {
            return false;
        }

        if ($user->can('roles.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        // Cannot delete a role the code refers to by name
        if ($role->isSystemRole()) {
            return false;
        }

        return $user->can('roles.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->can('roles.create');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->can('roles.delete');
    }
}
