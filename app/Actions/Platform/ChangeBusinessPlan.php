<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;

/**
 * Moves a business to another plan from the platform console (Phase 5, B1).
 * The new plan's price is locked from the next payment (no proration, as when
 * a business raises its shops mid-period, Phase 4); its limits and features
 * apply at once. A business with no subscription yet gets one, on the plan's
 * trial. Audited.
 */
class ChangeBusinessPlan
{
    public function execute(Business $business, Plan $plan): Subscription
    {
        return app(TenantContext::class)->run($business, function () use ($business, $plan): Subscription {
            $subscription = Subscription::query()->first();
            $before = $subscription?->plan_id;

            if ($subscription === null) {
                $subscription = Subscription::create([
                    'business_id' => $business->id,
                    'plan_id' => $plan->id,
                    'shop_count' => 1,
                    'price_at_signup' => $plan->price_per_shop,
                    'status' => Subscription::TRIALING,
                    'trial_ends_at' => now()->addDays($plan->trial_days),
                ]);
            } else {
                $subscription->update(['plan_id' => $plan->id, 'price_at_signup' => $plan->price_per_shop]);
            }

            PlatformAudit::record('business.plan_changed', $business, ['plan_before' => $before, 'plan' => $plan->id, 'price_per_shop' => (string) $plan->price_per_shop], AuditEvent::UPDATED);

            return $subscription;
        });
    }
}
