<?php

namespace App\Actions\Billing;

use App\Enums\Limit;
use App\Exceptions\PaymentRejected;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\PlanLimits;
use Illuminate\Support\Facades\DB;

/**
 * An owner choosing a tier during a trial, without paying (Phase 6, 2.6): the
 * plan, its limits and the shop count change at once and `trial_ends_at` stays
 * where it was, so the 14 days never restart however often the choice changes.
 * Also how a Free Trial business picks the tier it will pay for.
 *
 * Choosing a smaller plan follows the Phase 4 downgrade rule: more shops than
 * paid for makes every shop read-only until enough are deleted, and staff over
 * the new limit stay but nobody new is added. `warnings()` says so beforehand.
 * The change is audited through the subscription's own audit trail (who, from,
 * to, when).
 */
class ChangeTrialPlan
{
    public function __construct(private PlanLimits $limits) {}

    /**
     * What the change would put over the new plan's limits, as sentences; empty
     * when nothing is exceeded.
     *
     * @return list<string>
     */
    public function warnings(Subscription $subscription, Plan $plan, int $shopCount): array
    {
        $businessId = (int) $subscription->business_id;
        $shopCount = $plan->is_trial_plan ? 1 : $shopCount;
        $warnings = [];

        $shops = $this->limits->count(Limit::Shops, $businessId);
        if ($shops > $shopCount) {
            $warnings[] = __('You have :shops shops but would pay for :paid. Every shop would be read-only until you delete :extra (signing in, viewing, exporting and deleting shops still work).', [
                'shops' => $shops, 'paid' => $shopCount, 'extra' => $shops - $shopCount,
            ]);
        }

        $staffLimit = $plan->limit(Limit::Staff);
        $staff = $this->limits->count(Limit::Staff, $businessId);
        if ($staffLimit !== null && $staff > $staffLimit) {
            $warnings[] = __('You have :staff staff (invites included) but the plan allows :limit. They keep their access; nobody new can be added until you are within the limit.', [
                'staff' => $staff, 'limit' => $staffLimit,
            ]);
        }

        return $warnings;
    }

    /**
     * @throws PaymentRejected when the business is not on a trial
     */
    public function execute(Plan $plan, int $shopCount): Subscription
    {
        return DB::transaction(function () use ($plan, $shopCount): Subscription {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->firstOrFail();

            if ($subscription->status !== Subscription::TRIALING && ! $subscription->plan->is_trial_plan) {
                throw new PaymentRejected(__('Plans are changed here during a trial only.'));
            }

            $subscription->update([
                'plan_id' => $plan->id,
                'shop_count' => $plan->is_trial_plan ? 1 : max(1, $shopCount),
                'price_at_signup' => $plan->price_per_shop,
                // trial_ends_at and status are left alone on purpose.
            ]);

            return $subscription;
        });
    }
}
