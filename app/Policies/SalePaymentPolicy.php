<?php

namespace App\Policies;

use App\Models\SalePayment;
use App\Models\User;

class SalePaymentPolicy
{
    /**
     * Allow users with full access to bypass all checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('payments.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('payments.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SalePayment $payment): bool
    {
        return $user->can('payments.view') && $this->sharesShop($user, $payment);
    }

    /**
     * sale_payments has no shop_id of its own — ownership comes from the parent
     * sale, so a user must have access to that sale's shop.
     */
    private function sharesShop(User $user, SalePayment $payment): bool
    {
        $shopId = $payment->sale?->shop_id;

        if ($shopId === null) {
            return false;
        }

        return $user->canAccessShop($shopId);
    }
}
