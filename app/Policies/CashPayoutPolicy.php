<?php

namespace App\Policies;

use App\Models\CashRegister;
use App\Models\User;

/**
 * Cash paid out of a session by hand, for petty cash (stage 10, step 4):
 * someone holding `cash-registers.payout` with access to the shop, from an
 * open session, with a reason.
 */
class CashPayoutPolicy
{
    public function create(User $user, CashRegister $session): bool
    {
        return $user->can('cash-registers.payout')
            && $session->status === 'open'
            && $user->canAccessShop((int) $session->shop_id);
    }
}
