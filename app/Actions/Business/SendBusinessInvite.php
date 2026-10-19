<?php

namespace App\Actions\Business;

use App\Models\BusinessInvite;
use App\Models\User;
use App\Notifications\BusinessInvitation;
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
        $token = Str::random(40);

        $invite = DB::transaction(function () use ($data, $inviter, $token): BusinessInvite {
            BusinessInvite::query()->pending()->where('email', Str::lower($data['email']))
                ->update(['revoked_at' => now()]);

            return BusinessInvite::create([
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'shop_ids' => array_values(array_map('intval', $data['shop_ids'] ?? [])),
                'token_hash' => BusinessInvite::hashToken($token),
                'invited_by' => $inviter->id,
                'expires_at' => now()->addDays(BusinessInvite::EXPIRES_AFTER_DAYS),
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
        $token = Str::random(40);

        $invite->update([
            'token_hash' => BusinessInvite::hashToken($token),
            'expires_at' => now()->addDays(BusinessInvite::EXPIRES_AFTER_DAYS),
        ]);

        Notification::route('mail', $invite->email)->notify(new BusinessInvitation($invite, $token));

        return $invite;
    }
}
