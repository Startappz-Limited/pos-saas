<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;

/**
 * Starts a business's trial (Phase 4, decision 4.6; Phase 6): the Free Trial,
 * or a trial of the tier picked, counted from now. Without a choice it is the
 * top tier, paying for the one shop the business starts with (what existing
 * callers and the console expect). A business that already has a
 * subscription keeps it.
 */
class StartTrial
{
    public function __construct(private TenantContext $tenant) {}

    public function execute(Business $business, ?Plan $plan = null, int $shopCount = 1): ?Subscription
    {
        $plan ??= Plan::query()->tiers()->get()->last();

        if ($plan === null) {
            return null;
        }

        return $this->tenant->run($business, fn (): Subscription => Subscription::query()->firstOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id' => $plan->id,
                'shop_count' => $plan->is_trial_plan ? 1 : max(1, $shopCount),
                'price_at_signup' => $plan->price_per_shop,
                'status' => Subscription::TRIALING,
                'trial_ends_at' => now()->addDays($plan->trial_days ?: 14),
            ],
        ));
    }
}
