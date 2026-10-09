<?php

namespace App\Policies;

use App\Models\AbandonedCart;
use App\Models\User;

/**
 * Abandoned carts are shop data: every ability also requires access to the
 * cart's shop, including for `abandoned-carts.full-access` holders.
 */
class AbandonedCartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('abandoned-carts.view') || $user->can('abandoned-carts.full-access');
    }

    public function view(User $user, AbandonedCart $cart): bool
    {
        return $this->viewAny($user) && $user->canAccessShop($cart->shop_id);
    }

    /**
     * The live recovery link restores the customer's cart; it is shown only to
     * people who may act on the cart, never to read-only viewers.
     */
    public function viewRecoveryLink(User $user, AbandonedCart $cart): bool
    {
        return $this->update($user, $cart);
    }

    /**
     * Status, assignment, notes, reminders and opt-out.
     */
    public function update(User $user, AbandonedCart $cart): bool
    {
        return $this->has($user, 'abandoned-carts.update') && $user->canAccessShop($cart->shop_id);
    }

    public function contact(User $user, AbandonedCart $cart): bool
    {
        return $this->has($user, 'abandoned-carts.contact') && $user->canAccessShop($cart->shop_id);
    }

    public function convert(User $user, AbandonedCart $cart): bool
    {
        return $this->has($user, 'abandoned-carts.convert') && $user->canAccessShop($cart->shop_id);
    }

    private function has(User $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('abandoned-carts.full-access');
    }
}
