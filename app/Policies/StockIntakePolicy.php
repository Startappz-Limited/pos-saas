<?php

namespace App\Policies;

use App\Models\StockIntake;
use App\Models\User;

class StockIntakePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('stock-intake.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock-intake.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('stock-intake.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock-intake.update') && $stockIntake->canEdit();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock-intake.delete') && $stockIntake->isPending();
    }

    /**
     * Determine whether the user can complete the model.
     */
    /**
     * Completing receives the stock, so it needs the approval permission.
     */
    public function complete(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock-intake.approve') && $stockIntake->canComplete();
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock-intake.update') && $stockIntake->canCancel();
    }
}
