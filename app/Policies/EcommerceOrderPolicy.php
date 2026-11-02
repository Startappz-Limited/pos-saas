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

    /**
     * Permission only: whether the order can still be converted is checked by the
     * callers, which answer 422 "This order cannot be converted to a sale." rather
     * than a permission refusal (hotfix fix/api-authz).
     */
    public function convert(User $user, EcommerceOrder $order): bool
    {
        return ($user->can('ecommerce-orders.edit-all') || $user->can('ecommerce-orders.convert'))
            && $user->canAccessShop($order->shop_id);
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
