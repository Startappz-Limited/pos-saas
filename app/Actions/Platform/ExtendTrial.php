<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Business;
use App\Models\Subscription;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use DomainException;

/**
 * Extends a business's trial from the platform console (Phase 5, B1): by a
 * number of days from its current end, or from now when the trial has already
 * ended (which reopens a business read-only for a lapsed trial). Only a
 * subscription still on its trial can be extended. Audited.
 */
class ExtendTrial
{
    public const MAX_DAYS = 90;

    public function execute(Business $business, int $days): Subscription
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw new DomainException(__('A trial can be extended by 1 to :max days.', ['max' => self::MAX_DAYS]));
        }

        return app(TenantContext::class)->run($business, function () use ($business, $days): Subscription {
            $subscription = Subscription::query()->first();

            if ($subscription === null || $subscription->status !== Subscription::TRIALING) {
                throw new DomainException(__('Only a business on its trial can have the trial extended.'));
            }

            $before = $subscription->trial_ends_at;
            $from = $before !== null && $before->isFuture() ? $before : now();
            $subscription->update(['trial_ends_at' => $from->copy()->addDays($days)]);

            PlatformAudit::record('business.trial_extended', $business, [
                'days' => $days,
                'trial_ended_at_before' => $before?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at->toIso8601String(),
            ], AuditEvent::UPDATED);

            return $subscription;
        });
    }
}
