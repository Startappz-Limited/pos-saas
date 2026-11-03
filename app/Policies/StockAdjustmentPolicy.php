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
        // Approving is a second person's act (stage 10, step 1g): full access, the
        // owner's included, never lets the creator approve their own.
        if ($ability === 'approve') {
            return null;
        }

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
    /**
     * A second person approves, which applies the stock (stage 10, step 1g):
     * someone other than the creator holding `stock-adjustments.approve` (or full
     * access), whoever the creator is.
     */
    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        return ($user->can('stock-adjustments.approve') || $user->can('stock-adjustments.full-access'))
            && $stockAdjustment->canBeApproved()
            && (int) $stockAdjustment->created_by !== (int) $user->id;
    }

    public function reject(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $user->can('stock-adjustments.approve') && $stockAdjustment->canBeRejected();
    }
}
