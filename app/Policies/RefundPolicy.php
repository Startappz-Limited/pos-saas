<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;

class RefundPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('refunds.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('refunds.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Refund $refund): bool
    {
        if ($user->can('refunds.view-all') && $user->canAccessShop($refund->shop_id)) {
            return true;
        }

        return $user->can('refunds.view') && $user->canAccessShop($refund->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('refunds.create');
    }

    /**
     * Determine whether the user can process the refund.
     */
    public function process(User $user, Refund $refund): bool
    {
        return $user->can('refunds.approve') && $user->canAccessShop($refund->shop_id) && $refund->isPending();
    }
}
