<?php

namespace App\Policies;

use App\Models\LowStockAlert;
use App\Models\User;

class LowStockAlertPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('alerts.full-access')) {
            return true;
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
