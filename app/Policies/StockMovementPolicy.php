<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('stock-movements.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('stock-movements.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $user->can('stock-movements.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false; // Stock movements are system-generated
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockMovement $stockMovement): bool
    {
        return false; // Stock movements are immutable
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return false; // Stock movements are immutable
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StockMovement $stockMovement): bool
    {
        return false;
    }
}
