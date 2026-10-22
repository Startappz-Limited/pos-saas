<?php

namespace App\Actions\Business;

use App\Exceptions\SubscriptionInactive;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Role;
use App\Models\Scopes\ShopAccessScope;
use App\Models\Shop;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Accepts an invite for the login with its email (decision 4.4, stage 3): a
 * membership in the business (restored if the person was a member before),
 * the invite's role held in that business, and its shops that still exist.
 * Accepting proves the email address, so an unverified one becomes verified.
 */
class AcceptBusinessInvite
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Judged in the invite's business: a read-only business takes no new
     * people, and a role switched off since the invite was sent is given to
     * nobody. Also asked before a new login is made for the invite.
     *
     * @throws SubscriptionInactive
     * @throws InvalidArgumentException
     */
    public function assertAcceptable(BusinessInvite $invite): void
    {
        $this->tenant->run($invite->business_id, function () use ($invite): void {
            $reason = app(Entitlements::class)->readOnlyReason();

            if ($reason !== null) {
                throw new SubscriptionInactive($reason);
            }

            $role = Role::query()->find($invite->role_id);

            if ($role !== null && ! $role->is_active) {
                throw new InvalidArgumentException('The role for this invitation has been switched off. Ask your admin to send a new invitation.');
            }
        });
    }

    /**
     * @throws InvalidArgumentException when the invite isn't pending or is for another email
     */
    public function execute(BusinessInvite $invite, User $user): void
    {
        if (Str::lower($user->email) !== $invite->email) {
            throw new InvalidArgumentException('This invitation is for another email address.');
        }

        DB::transaction(function () use ($invite, $user): void {
            $invite = BusinessInvite::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($invite->id);

            if (! $invite->isPending()) {
                throw new InvalidArgumentException('This invitation is no longer valid.');
            }

            $this->assertAcceptable($invite);

            // Spent first: a pending invite holds a staff seat, and the member
            // it becomes must not be counted against the plan twice.
            $invite->update(['accepted_at' => now(), 'accepted_by' => $user->id]);

            $this->tenant->run($invite->business_id, function () use ($invite, $user): void {
                $membership = $user->joinBusiness($invite->business, $invite->inviter);

                if ($membership->status !== BusinessMembership::STATUS_ACTIVE) {
                    $membership->update(['status' => BusinessMembership::STATUS_ACTIVE]);
                }

                $role = Role::query()->find($invite->role_id);
                if ($role !== null) {
                    $user->assignRole($role);
                }

                // The joiner can't see any shop yet (default-deny), so only the
                // business scope limits this: the invite's shops still in it.
                $shopIds = Shop::query()->withoutGlobalScope(ShopAccessScope::class)
                    ->whereIn('id', $invite->shop_ids ?? [])->pluck('id');
                $user->shops()->syncWithoutDetaching($shopIds);
            });

            if (! $user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
        });
    }
}
