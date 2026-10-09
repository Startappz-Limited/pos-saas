<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('stock-adjustments.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('stock-adjustments.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('stock-adjustments.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.update') && $stockAdjustment->isPending();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.delete') && $stockAdjustment->isPending();
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.approve') && $stockAdjustment->canBeApproved();
    }

    /**
     * Determine whether the user can reject the model.
     */
    public function reject(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.approve') && $stockAdjustment->canBeRejected();
    }

    /**
     * Determine whether the user can complete the model.
     */
    public function complete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.approve') && $stockAdjustment->canBeCompleted();
    }
}
