<?php

namespace App\Policies;

use App\Models\EcommerceOrder;
use App\Models\User;

class EcommerceOrderPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('ecommerce-orders.full-access')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ecommerce-orders.view');
    }

    public function view(User $user, EcommerceOrder $order): bool
    {
        if ($user->can('ecommerce-orders.view-all') && $user->canAccessShop($order->shop_id)) {
            return true;
        }

        return $user->can('ecommerce-orders.view') && $user->canAccessShop($order->shop_id);
    }

    public function convert(User $user, EcommerceOrder $order): bool
    {
        if ($user->can('ecommerce-orders.edit-all') && $user->canAccessShop($order->shop_id)) {
            return $order->can_be_converted;
        }

        return $user->can('ecommerce-orders.convert') &&
            $user->canAccessShop($order->shop_id) &&
            $order->can_be_converted;
    }

    public function updateStatus(User $user, EcommerceOrder $order): bool
    {
        if ($user->can('ecommerce-orders.edit-all') && $user->canAccessShop($order->shop_id)) {
            return true;
        }

        return $user->can('ecommerce-orders.update') && $user->canAccessShop($order->shop_id);
    }

    public function refresh(User $user): bool
    {
        return $user->can('ecommerce-orders.view');
    }
}
