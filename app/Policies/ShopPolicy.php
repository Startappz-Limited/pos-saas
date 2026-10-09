<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class ShopPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'shops', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('shops.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Shop $shop): bool
    {
        return $user->can('shops.view') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('shops.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Shop $shop): bool
    {
        return $user->can('shops.update') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Shop $shop): bool
    {
        return $user->can('shops.delete') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can activate the model.
     */
    public function activate(User $user, Shop $shop): bool
    {
        return $user->can('shops.activate') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can deactivate the model.
     */
    public function deactivate(User $user, Shop $shop): bool
    {
        return $user->can('shops.activate') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can suspend the model.
     */
    public function suspend(User $user, Shop $shop): bool
    {
        return $user->can('shops.activate') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Shop $shop): bool
    {
        return $user->can('shops.delete') && $user->canAccessShop($shop->id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Shop $shop): bool
    {
        return $user->can('shops.delete') && $user->canAccessShop($shop->id);
    }
}
