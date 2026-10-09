<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class PurchaseOrderPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'purchase_orders', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('purchase_orders.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.update') && $purchaseOrder->canEdit();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.delete') && $purchaseOrder->isDraft();
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.approve') && $purchaseOrder->canApprove();
    }

    /**
     * Determine whether the user can approve and mark the model as ordered.
     */
    public function approveAndMarkAsOrdered(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.approve')
            && $user->can('purchase_orders.update')
            && $purchaseOrder->canApprove();
    }

    /**
     * Determine whether the user can submit the model for approval.
     */
    public function submitForApproval(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.update') && $purchaseOrder->isDraft();
    }

    /**
     * Determine whether the user can submit, approve, and mark the model as ordered.
     */
    public function submitApproveAndMarkAsOrdered(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.update')
            && $user->can('purchase_orders.approve')
            && $purchaseOrder->isDraft();
    }

    /**
     * Determine whether the user can mark the model as ordered.
     */
    public function markAsOrdered(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.update') && $purchaseOrder->isApproved();
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->can('purchase_orders.update') && $purchaseOrder->canCancel();
    }
}
