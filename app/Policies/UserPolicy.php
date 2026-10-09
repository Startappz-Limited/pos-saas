<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     *
     * A super-admin account can only be viewed or managed by another
     * super-admin, and a user of another business by nobody else. A
     * business owner can be changed only by themselves (co-admins cannot
     * demote, suspend or delete them), and only the owner deletes users. These checks run before the
     * full-access bypass because the admin (shop owner) role holds
     * users.full-access. Super-admins never reach this method; Gate::before
     * lets them through first.
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        $target = $arguments[0] ?? null;

        if ($target instanceof User && $target->isSuperAdmin()) {
            return false;
        }

        // Users of another business are off limits, whatever the permissions
        if ($target instanceof User && ! User::query()->visibleTo($user)->whereKey($target->getKey())->exists()) {
            return false;
        }

        if ($target instanceof User && $target->isNot($user) && $ability !== 'view' && $target->isBusinessOwner()) {
            return false;
        }

        // Deleting an account erases the person's data, so it is reserved for
        // the business owner: co-admins and managers deactivate or suspend
        // instead. Nobody deletes their own account, and a super-admin never
        // reaches here (Gate::before refuses them deletion outright).
        if ($target instanceof User && in_array($ability, ['delete', 'forceDelete'], true)
            && ($target->is($user) || ! $user->ownsBusinessOf($target))) {
            return false;
        }

        if ($user->can('users.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete yourself
        if ($user->id === $model->id) {
            return false;
        }

        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }
}
