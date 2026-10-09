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
        return $user->can('stock_intakes.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock_intakes.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('stock_intakes.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock_intakes.update') && $stockIntake->canEdit();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock_intakes.delete') && $stockIntake->isPending();
    }

    /**
     * Determine whether the user can complete the model.
     */
    public function complete(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock_intakes.complete') && $stockIntake->canComplete();
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, StockIntake $stockIntake): bool
    {
        return $user->can('stock_intakes.update') && $stockIntake->canCancel();
    }
}
