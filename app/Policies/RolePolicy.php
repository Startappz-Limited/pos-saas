<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * A business manages only its own roles (Phase 5). Shared templates and the
 * owner role are read-only from a business: only the platform operator changes
 * them, through the console. `roles.full-access` counts as each `roles.*`
 * permission and never passes these protections. A non-owner can't change a
 * role they hold, so nobody raises their own permissions by editing a role.
 *
 * Another business's role never reaches the policy: route binding doesn't
 * find it (BusinessOrSharedScope), so it is a 404.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->holds($user, 'roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->holds($user, 'roles.view');
    }

    public function create(User $user): bool
    {
        return $this->holds($user, 'roles.create');
    }

    public function update(User $user, Role $role): Response
    {
        return $this->changeable($user, $role, 'roles.update');
    }

    public function delete(User $user, Role $role): Response
    {
        return $this->changeable($user, $role, 'roles.delete');
    }

    public function restore(User $user, Role $role): Response
    {
        return $this->changeable($user, $role, 'roles.create');
    }

    public function forceDelete(User $user, Role $role): Response
    {
        return $this->delete($user, $role);
    }

    /**
     * Whether the user holds $permission, or `roles.full-access` in its place.
     */
    private function holds(User $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('roles.full-access');
    }

    /**
     * Whether $role is one of this business's own roles that $user may change
     * with $permission.
     */
    private function changeable(User $user, Role $role, string $permission): Response
    {
        if (! $this->holds($user, $permission)) {
            return Response::deny();
        }

        if ($role->isOwnerRole()) {
            return Response::deny(__('The Owner role can\'t be changed.'));
        }

        if ($role->isShared()) {
            return Response::deny(__('Built-in role templates can only be changed by the platform.'));
        }

        if (! $role->belongsToCurrentBusiness()) {
            return Response::denyAsNotFound();
        }

        if (! $user->isBusinessOwner() && $user->hasRole($role)) {
            return Response::deny(__('You can\'t change a role you hold.'));
        }

        return Response::allow();
    }
}
