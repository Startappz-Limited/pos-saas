<?php

namespace App\Actions\Billing;

use App\Actions\Business\CloseBusiness;
use App\Exceptions\PaymentRejected;
use App\Jobs\RetryBlockedAiWork;
use App\Models\AiBlockedWork;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Services\Billing\AiCredits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Makes a confirmed platform payment take effect, exactly once (Phase 4).
 *
 * `applied_at` is set in the same transaction, under a row lock, so a repeated
 * callback, a double click or two workers racing apply it once. A subscription
 * payment buys whole months at the price locked at signup for the shops paid
 * for, counted from the end of the running period (or from now, when it has
 * lapsed); paying a lapsed business also reopens it if it was closed for the
 * lapse and not yet erased. An AI top-up adds credit and releases the work
 * that was waiting for it.
 */
class ApplyPlatformPayment
{
    public function __construct(
        private TenantContext $tenant,
        private AiCredits $credits,
        private CloseBusiness $closer,
    ) {}

    /**
     * @return bool whether this call applied it (false: it already had been)
     *
     * @throws PaymentRejected
     */
    public function execute(PlatformPayment $payment): bool
    {
        $applied = $this->tenant->run($payment->business_id, fn (): bool => DB::transaction(function () use ($payment): bool {
            $locked = PlatformPayment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($locked->isApplied()) {
                return false;
            }

            if ($locked->status !== PlatformPayment::CONFIRMED) {
                throw new PaymentRejected(__('The payment is not confirmed.'));
            }

            match ($locked->purpose) {
                PlatformPayment::SUBSCRIPTION => $this->applySubscription($locked),
                PlatformPayment::AI_TOPUP => $this->applyTopUp($locked),
                default => throw new PaymentRejected(__('Unknown payment purpose.')),
            };

            $locked->update(['applied_at' => now()]);

            return true;
        }));

        if ($applied) {
            $this->tenant->run($payment->business_id, fn () => $payment->refresh());
            $this->afterApplied($payment);
        }

        return $applied;
    }

    private function applySubscription(PlatformPayment $payment): void
    {
        $subscription = Subscription::query()->lockForUpdate()->first()
            ?? throw new PaymentRejected(__('This business has no subscription.'));

        $monthly = $subscription->monthlyTotal();
        $months = $monthly > 0 ? (int) floor(((float) $payment->amount + 0.005) / $monthly) : 1;

        if ($months < 1) {
            throw new PaymentRejected(__('The payment is less than one month (:amount).', ['amount' => number_format($monthly, 2)]));
        }

        // From the end of what is already paid for, never from before now.
        $from = $subscription->isInGoodStanding() ? $subscription->endsAt() : now();

        $subscription->update([
            'status' => Subscription::ACTIVE,
            'current_period_end' => $from->copy()->addMonthsNoOverflow($months),
            'renewal_reminded_at' => null,
            'lapse_warned_at' => null,
        ]);
    }

    private function applyTopUp(PlatformPayment $payment): void
    {
        if ((float) $payment->amount < (float) config('billing.ai_min_topup', 500)) {
            throw new PaymentRejected(__('The minimum AI top-up is KES :min.', ['min' => config('billing.ai_min_topup', 500)]));
        }

        $this->credits->topUp($payment->business_id, $this->credits->creditsFor((float) $payment->amount), $payment->recorded_by);
    }

    /**
     * Effects that must not be undone if they fail: reopening a business that
     * was closed for the lapse, and re-running AI work that waited for credit.
     */
    private function afterApplied(PlatformPayment $payment): void
    {
        if ($payment->purpose === PlatformPayment::SUBSCRIPTION) {
            $closure = BusinessClosure::query()->open()
                ->where('business_id', $payment->business_id)
                ->where('reason', BusinessClosure::REASON_LAPSED)
                ->first();

            $business = Business::query()->find($payment->business_id);

            if ($closure !== null && $business?->status === Business::STATUS_CLOSING) {
                $this->closer->cancel($closure, null);
            }

            return;
        }

        $this->tenant->run($payment->business_id, function (): void {
            AiBlockedWork::query()->where('status', AiBlockedWork::WAITING)->oldest()->pluck('id')
                ->each(fn (int $id) => RetryBlockedAiWork::dispatch($id));
        });
    }
}
