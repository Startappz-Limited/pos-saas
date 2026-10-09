<?php

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class PurchaseReturnPolicy
{
    use HandlesFullAccess;

    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'purchase_returns', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_returns.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseReturn $purchaseReturn): bool
    {
        if ($user->can('purchase_returns.view-all') && $user->canAccessShop($purchaseReturn->shop_id)) {
            return true;
        }

        return $user->can('purchase_returns.view') && $user->canAccessShop($purchaseReturn->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('purchase_returns.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseReturn $purchaseReturn): bool
    {
        if ($user->can('purchase_returns.edit-all') && $user->canAccessShop($purchaseReturn->shop_id)) {
            return $purchaseReturn->canEdit();
        }

        return $user->can('purchase_returns.update')
            && $user->canAccessShop($purchaseReturn->shop_id)
            && $purchaseReturn->canEdit();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        if ($user->can('purchase_returns.delete-all') && $user->canAccessShop($purchaseReturn->shop_id)) {
            return $purchaseReturn->canEdit();
        }

        return $user->can('purchase_returns.delete')
            && $user->canAccessShop($purchaseReturn->shop_id)
            && $purchaseReturn->canEdit();
    }

    public function approve(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.approve') && $user->canAccessShop($purchaseReturn->shop_id) && $purchaseReturn->canApprove();
    }

    public function ship(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.ship') && $user->canAccessShop($purchaseReturn->shop_id) && $purchaseReturn->canShip();
    }

    public function approveAndShip(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.approve')
            && $user->can('purchase_returns.ship')
            && $user->canAccessShop($purchaseReturn->shop_id)
            && $purchaseReturn->canApprove();
    }

    public function complete(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.complete') && $user->canAccessShop($purchaseReturn->shop_id) && $purchaseReturn->canComplete();
    }

    public function cancel(User $user, PurchaseReturn $purchaseReturn): bool
    {
        return $user->can('purchase_returns.cancel') && $user->canAccessShop($purchaseReturn->shop_id) && $purchaseReturn->canCancel();
    }
}
