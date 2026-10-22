<?php

namespace App\Actions\User;

use App\Actions\Business\RegisterBusiness;
use App\Models\User;
use App\Notifications\EmailAddressChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Changes a login's email (Phase 6): the new address starts unverified with a
 * fresh 24-hour grace period, a verification link goes to it, and the old
 * address is told. Used by the verification page and the profile.
 */
class ChangeUserEmail
{
    public function execute(User $user, string $email): void
    {
        $old = $user->email;

        if (mb_strtolower($email) === mb_strtolower($old)) {
            return;
        }

        DB::transaction(function () use ($user, $email): void {
            $user->forceFill([
                'email' => $email,
                'email_verified_at' => null,
                'verification_deadline_at' => now()->addHours(RegisterBusiness::VERIFICATION_GRACE_HOURS),
            ])->save();
        });

        $user->sendEmailVerificationNotification();

        Notification::route('mail', $old)->notify(new EmailAddressChanged($user->name, $email));
    }
}
