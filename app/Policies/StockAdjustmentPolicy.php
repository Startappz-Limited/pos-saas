<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;
use App\Support\Stock\AdjustmentApproval;

class StockAdjustmentPolicy
{
    /**
     * Full access stands in for the permission of each ability, never for the
     * state checks: only a pending adjustment is edited or cancelled, and
     * approving is a second person's act (stage 10, step 1g).
     */
    private function holds(User $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('stock-adjustments.full-access');
    }

    public function viewAny(User $user): bool
    {
        return $this->holds($user, 'stock-adjustments.view');
    }

    public function view(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $this->holds($user, 'stock-adjustments.view');
    }

    public function create(User $user): bool
    {
        return $this->holds($user, 'stock-adjustments.create');
    }

    /**
     * Only a pending adjustment is edited; an applied, rejected or cancelled one
     * is history.
     */
    public function update(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $this->holds($user, 'stock-adjustments.update') && $stockAdjustment->isPending();
    }

    /**
     * Cancelling replaces deleting: a pending adjustment is withdrawn by its
     * creator or by someone holding the delete permission, and keeps its number.
     */
    public function cancel(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $stockAdjustment->isPending()
            && ((int) $stockAdjustment->created_by === (int) $user->id || $this->holds($user, 'stock-adjustments.delete'));
    }

    /**
     * Rows are never deleted (their numbers would leave gaps); see cancel().
     */
    public function delete(User $user, StockAdjustment $stockAdjustment): bool
    {
        return false;
    }

    /**
     * Approving applies the stock (stage 10, step 1g; revised 3 November 2026):
     * someone holding the approve permission in this business who may work in
     * the adjustment's shop, other than its creator. Its creator may only as
     * an owner whom nobody else could relieve (with a note).
     */
    public function approve(User $user, StockAdjustment $stockAdjustment): bool
    {
        if (! $stockAdjustment->canBeApproved()
            || ! $this->holds($user, 'stock-adjustments.approve')
            || ! $user->canAccessShop((int) $stockAdjustment->shop_id)) {
            return false;
        }

        return (int) $stockAdjustment->created_by !== (int) $user->id
            || app(AdjustmentApproval::class)->mayApproveOwn($user, $stockAdjustment);
    }

    /**
     * Rejecting, with a reason, by the same people who may approve; a creator
     * withdraws their own by cancelling it.
     */
    public function reject(User $user, StockAdjustment $stockAdjustment): bool
    {
        return $stockAdjustment->canBeRejected()
            && $this->holds($user, 'stock-adjustments.approve')
            && $user->canAccessShop((int) $stockAdjustment->shop_id)
            && (int) $stockAdjustment->created_by !== (int) $user->id;
    }
}
