<?php

namespace App\Policies;

use App\Models\RefundDue;
use App\Models\User;
use App\Support\Approvals\Approvers;
use App\Support\Sales\VoidRefunds;

/**
 * Refunds owed after a void (stage 10, step 4; decided 4 November 2026):
 * listed for owners and the people who may pay them, and paid (or, for a
 * cheque, settled as returned) by someone holding `sales.void` or
 * `refunds.approve` with access to the shop.
 */
class RefundDuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isBusinessOwner() || app(Approvers::class)->holds($user, VoidRefunds::PAYERS);
    }

    public function pay(User $user, RefundDue $refund): bool
    {
        return $refund->isPending()
            && app(Approvers::class)->holds($user, VoidRefunds::PAYERS)
            && $user->canAccessShop((int) $refund->shop_id);
    }
}
