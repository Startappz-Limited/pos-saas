<?php

namespace App\Listeners\Auth;

use App\Actions\Business\StartTrial;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * The trial starts only once the owner's email is verified (stage 3): every
 * business the user signed up for opens, its trial starts now, and the owner's
 * membership in it is active.
 */
class StartTrialOnEmailVerified
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // Every business the user signed up for that waits for this.
        $pending = Business::query()
            ->whereIn('id', $event->user->memberships()->select('business_id'))
            ->where('status', Business::STATUS_PENDING_VERIFICATION)
            ->get();

        foreach ($pending as $business) {
            $business->update(['status' => Business::STATUS_ACTIVE, 'trial_started_at' => now()]);

            // The owner works in it from now on, whatever their membership said.
            $event->user->memberships()->where('business_id', $business->id)
                ->update(['status' => BusinessMembership::STATUS_ACTIVE]);

            // The trial, as a subscription on the top tier (Phase 4).
            app(StartTrial::class)->execute($business);
        }
    }
}
