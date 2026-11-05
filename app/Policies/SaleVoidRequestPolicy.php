<?php

namespace App\Policies;

use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Support\Approvals\Approvers;
use App\Support\Sales\VoidRequests;

/**
 * Void requests under the shared approval rule (stage 10, step 2): settled by
 * someone holding `sales.void` in the business who may work in the shop,
 * never the requester (an owner nobody else could relieve excepted); withdrawn
 * by the requester.
 */
class SaleVoidRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view') || app(Approvers::class)->holds($user, VoidRequests::PERMISSIONS);
    }

    public function view(User $user, SaleVoidRequest $request): bool
    {
        return $user->canAccessShop((int) $request->shop_id)
            && ((int) $request->requested_by === (int) $user->id || app(Approvers::class)->holds($user, VoidRequests::PERMISSIONS));
    }

    public function approve(User $user, SaleVoidRequest $request): bool
    {
        if (! $request->isPending()
            || ! app(Approvers::class)->holds($user, VoidRequests::PERMISSIONS)
            || ! $user->canAccessShop((int) $request->shop_id)) {
            return false;
        }

        return (int) $request->requested_by !== (int) $user->id
            || app(Approvers::class)->mayApproveOwn($user, VoidRequests::PERMISSIONS, (int) $request->shop_id);
    }

    public function reject(User $user, SaleVoidRequest $request): bool
    {
        return $request->isPending()
            && app(Approvers::class)->holds($user, VoidRequests::PERMISSIONS)
            && $user->canAccessShop((int) $request->shop_id)
            && (int) $request->requested_by !== (int) $user->id;
    }

    public function cancel(User $user, SaleVoidRequest $request): bool
    {
        return $request->isPending() && (int) $request->requested_by === (int) $user->id;
    }
}
