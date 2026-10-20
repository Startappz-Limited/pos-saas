<?php

namespace App\Listeners\Auth;

use App\Actions\Business\StartTrial;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * The trial starts only once the owner's email is verified (stage 3): every
 * business the user signed up for opens, and its trial starts now.
 */
class StartTrialOnEmailVerified
{
    public function handle(Verified $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $businessIds = $event->user->memberships()
            ->where('status', BusinessMembership::STATUS_ACTIVE)
            ->pluck('business_id');

        $pending = Business::query()
            ->whereIn('id', $businessIds)
            ->where('status', Business::STATUS_PENDING_VERIFICATION)
            ->get();

        foreach ($pending as $business) {
            $business->update(['status' => Business::STATUS_ACTIVE, 'trial_started_at' => now()]);

            // The trial, as a subscription on the top tier (Phase 4).
            app(StartTrial::class)->execute($business);
        }
    }
}
