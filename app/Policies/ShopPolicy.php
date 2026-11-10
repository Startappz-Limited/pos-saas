<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

class ShopPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('shops.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Viewing follows access (stage 10a, step 3): any member of the
     * business may see the shops they can reach, whatever their role.
     * `shops.view` stays as a permission for a business that wants to show
     * more of the list to a role, but no longer gates seeing one's own.
     */
    public function viewAny(User $user): bool
    {
        return app(TenantContext::class)->has();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Shop $shop): bool
    {
        return $user->canAccessShop($shop->id);
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
