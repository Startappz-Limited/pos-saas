<?php

namespace App\Actions\Billing;

use App\Actions\Business\CloseBusiness;
use App\Actions\Business\CopyRoleTemplates;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminder;
use App\Services\Billing\BusinessDays;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * The daily subscription sweep (Phase 4, decisions 4.6 and 4.9):
 *
 * - reminds the owners a few days before a trial or paid period ends;
 * - once it has lapsed, warns them a few business days before the grace period
 *   runs out;
 * - when the grace period (30 business days) is over, starts the closure
 *   flow, which emails the final export and erases after its own grace period.
 *
 * Platform-wide: it looks at every active business with a subscription.
 * Idempotent: each notice is sent once per subscription period.
 */
class SweepSubscriptions
{
    private const REMIND_DAYS_BEFORE = 3;

    public function __construct(
        private TenantContext $tenant,
        private BusinessDays $days,
        private CloseBusiness $closer,
    ) {}

    /**
     * @return array{reminded: int, warned: int, closed: int}
     */
    public function execute(): array
    {
        $result = ['reminded' => 0, 'warned' => 0, 'closed' => 0];

        $businessIds = Subscription::query()->withoutGlobalScopes()->pluck('business_id');

        foreach (Business::query()->whereIn('id', $businessIds)->where('status', Business::STATUS_ACTIVE)->get() as $business) {
            try {
                $this->tenant->run($business, function () use ($business, &$result): void {
                    $this->sweep($business, $result);
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /**
     * @param  array{reminded: int, warned: int, closed: int}  $result
     */
    private function sweep(Business $business, array &$result): void
    {
        $entitlements = app(Entitlements::class);
        $subscription = $entitlements->subscription();
        $endsAt = $subscription?->endsAt();

        if ($subscription === null || $endsAt === null) {
            return;
        }

        if ($subscription->isInGoodStanding()) {
            if ($subscription->renewal_reminded_at === null && $endsAt->lte(now()->addDays(self::REMIND_DAYS_BEFORE))) {
                $this->notifyOwners($business, new SubscriptionReminder(SubscriptionReminder::RENEWAL, $business->name, $endsAt));
                $subscription->update(['renewal_reminded_at' => now()]);
                $result['reminded']++;
            }

            // A trial gets a second, last reminder the day before it ends (Phase 6).
            if ($subscription->status === Subscription::TRIALING
                && $subscription->trial_final_reminded_at === null
                && $endsAt->lte(now()->addDay())) {
                $this->notifyOwners($business, new SubscriptionReminder(SubscriptionReminder::RENEWAL, $business->name, $endsAt));
                $subscription->update(['trial_final_reminded_at' => now()]);
                $result['reminded']++;
            }

            return;
        }

        $graceEnds = $entitlements->graceEndsAt();

        if ($graceEnds->copy()->endOfDay()->isPast()) {
            $this->closer->start($business, null, BusinessClosure::REASON_LAPSED);
            $result['closed']++;

            return;
        }

        if ($subscription->lapse_warned_at === null && now()->gte($this->warnDate($graceEnds))) {
            $this->notifyOwners($business, new SubscriptionReminder(SubscriptionReminder::CLOSURE, $business->name, $graceEnds));
            $subscription->update(['lapse_warned_at' => now()]);
            $result['warned']++;
        }
    }

    /**
     * The first day the closure warning goes out: the configured number of
     * business days before the grace period ends.
     */
    private function warnDate(Carbon $graceEnds): Carbon
    {
        $date = $graceEnds->copy()->startOfDay();
        $remaining = (int) config('billing.warn_business_days_before', 5);

        while ($remaining > 0) {
            $date->subDay();

            if ($this->days->isBusinessDay($date)) {
                $remaining--;
            }
        }

        return $date;
    }

    private function notifyOwners(Business $business, SubscriptionReminder $notification): void
    {
        $owners = User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->get();

        Notification::send($owners, $notification);
    }
}
