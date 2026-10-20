<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;

/**
 * Starts a business's trial (Phase 4, decision 4.6): on the top tier, so the
 * owner sees the whole product, paying for the one shop it starts with. Runs
 * once the owner's email is verified; a business that already has a
 * subscription keeps it.
 */
class StartTrial
{
    public function __construct(private TenantContext $tenant) {}

    public function execute(Business $business): ?Subscription
    {
        $plan = Plan::query()->where('is_active', true)->orderByDesc('sort_order')->first();

        if ($plan === null) {
            return null;
        }

        return $this->tenant->run($business, fn (): Subscription => Subscription::query()->firstOrCreate(
            ['business_id' => $business->id],
            [
                'plan_id' => $plan->id,
                'shop_count' => 1,
                'price_at_signup' => $plan->price_per_shop,
                'status' => Subscription::TRIALING,
                'trial_ends_at' => now()->addDays($plan->trial_days),
            ],
        ));
    }
}
