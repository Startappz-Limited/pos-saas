<?php

namespace App\Policies;

use App\Models\SaleReturn;
use App\Models\User;

class ReturnPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('returns.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('returns.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SaleReturn $saleReturn): bool
    {
        if ($user->can('returns.view-all') && $user->canAccessShop($saleReturn->shop_id)) {
            return true;
        }

        return $user->can('returns.view') && $user->canAccessShop($saleReturn->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('returns.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.update') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->isPending();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.delete') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->isPending();
    }

    /**
     * Determine whether the user can approve the model.
     */
    public function approve(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.approve') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->canBeApproved();
    }

    /**
     * Determine whether the user can reject the model.
     */
    public function reject(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.approve') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->canBeRejected();
    }

    /**
     * Determine whether the user can receive the return.
     */
    public function receive(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.update') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->canBeReceived();
    }

    /**
     * Determine whether the user can inspect the return.
     */
    public function inspect(User $user, SaleReturn $saleReturn): bool
    {
        return $user->can('returns.update') && $user->canAccessShop($saleReturn->shop_id) && $saleReturn->canBeInspected();
    }
}
