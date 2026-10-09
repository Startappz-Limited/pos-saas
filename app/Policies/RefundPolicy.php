<?php

namespace App\Policies;

use App\Models\Refund;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class RefundPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'refunds', $ability, $arguments)) !== null) {
            return $decision;
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
