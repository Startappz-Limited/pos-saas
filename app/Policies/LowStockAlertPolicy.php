<?php

namespace App\Policies;

use App\Models\LowStockAlert;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class LowStockAlertPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'alerts', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('alerts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.delete');
    }

    /**
     * Determine whether the user can acknowledge the alert.
     */
    public function acknowledge(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.update') && ! $lowStockAlert->isAcknowledged();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LowStockAlert $lowStockAlert): bool
    {
        return $user->can('alerts.delete');
    }
}
