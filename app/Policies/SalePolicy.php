<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class SalePolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'sales', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Sale $sale): bool
    {
        if ($user->can('sales.view-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.view') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sale $sale): bool
    {
        if ($user->can('sales.edit-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.update') &&
            $user->canAccessShop($sale->shop_id) &&
            $sale->created_at->diffInHours(now()) < 24;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sale $sale): bool
    {
        if ($user->can('sales.delete-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.delete') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can collect payment for the sale.
     */
    public function collectPayment(User $user, Sale $sale): bool
    {
        if ($user->can('sales.edit-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.update') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can void the sale.
     */
    public function void(User $user, Sale $sale): bool
    {
        // Sale must not already be voided
        if ($sale->status === 'voided') {
            return false;
        }

        if ($user->can('sales.edit-all') && $user->canAccessShop($sale->shop_id)) {
            return true;
        }

        return $user->can('sales.void') && $user->canAccessShop($sale->shop_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sale $sale): bool
    {
        return $user->can('sales.restore');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sale $sale): bool
    {
        return $user->can('sales.delete-all');
    }
}
