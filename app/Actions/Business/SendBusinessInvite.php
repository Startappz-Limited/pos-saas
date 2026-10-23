<?php

namespace App\Actions\Business;

use App\Enums\Limit;
use App\Exceptions\SubscriptionInactive;
use App\Models\BusinessInvite;
use App\Models\User;
use App\Notifications\BusinessInvitation;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanLimits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Invites a person to the current business by email (stage 3). A pending
 * invite to the same email is replaced, so only the newest link works.
 */
class SendBusinessInvite
{
    /**
     * @param  array{email: string, role_id: int, shop_ids?: list<int>}  $data
     */
    public function execute(array $data, User $inviter): BusinessInvite
    {
        $this->assertWritable();

        $token = Str::random(40);

        $invite = DB::transaction(function () use ($data, $inviter, $token): BusinessInvite {
            BusinessInvite::query()->pending()->where('email', Str::lower($data['email']))
                ->update(['revoked_at' => now()]);

            // A pending invite holds a staff seat (Phase 6): checked after the
            // one it replaces is withdrawn, so re-inviting never needs a second seat.
            app(PlanLimits::class)->assertCanAdd(Limit::Staff, (int) app(TenantContext::class)->id());

            return BusinessInvite::create([
                'email' => $data['email'],
                'name' => filled($data['name'] ?? null) ? $data['name'] : null,
                'role_id' => $data['role_id'],
                'shop_ids' => array_values(array_map('intval', $data['shop_ids'] ?? [])),
                'token_hash' => BusinessInvite::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addHours(BusinessInvite::EXPIRES_AFTER_HOURS),
            ]);
        });

        Notification::route('mail', $invite->email)->notify(new BusinessInvitation($invite, $token));

        return $invite;
    }

    /**
     * Sends a fresh link for a pending invite (the old link stops working) and
     * gives it a new expiry.
     */
    public function resend(BusinessInvite $invite): BusinessInvite
    {
        $this->assertWritable();

        $token = Str::random(40);

        $invite->update([
            'token_hash' => BusinessInvite::hashToken($token),
            'expires_at' => now()->addHours(BusinessInvite::EXPIRES_AFTER_HOURS),
        ]);

        Notification::route('mail', $invite->email)->notify(new BusinessInvitation($invite, $token));

        return $invite;
    }

    /**
     * Sending and resending are refused while the business is read-only
     * (lapsed or over its shop quota), with `subscription_inactive`.
     *
     * @throws SubscriptionInactive
     */
    private function assertWritable(): void
    {
        $reason = app(Entitlements::class)->readOnlyReason();

        if ($reason !== null) {
            throw new SubscriptionInactive($reason);
        }
    }
}
