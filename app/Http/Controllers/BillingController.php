<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ChangeTrialPlan;
use App\Actions\Billing\ConfirmPlatformPayment;
use App\Actions\Billing\RequestPlatformPayment;
use App\Contracts\PaymentCollector;
use App\Enums\Limit;
use App\Exceptions\PaymentRejected;
use App\Models\AiUsageEvent;
use App\Models\Plan;
use App\Models\PlatformPayment;
use App\Services\Billing\AiCredits;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanLimits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The billing page (Phase 4): the plan and its state, paying for the
 * subscription, the shops paid for, and the prepaid AI credit with its
 * top-up. Owners only. Reachable while the business is read-only, so a lapsed
 * owner can pay.
 */
class BillingController extends Controller
{
    public function __construct(private TenantContext $tenant, private Entitlements $entitlements) {}

    public function show(Request $request, AiCredits $credits, PlanLimits $limits, PaymentCollector $collector): View
    {
        $this->ownersOnly($request);

        $subscription = $this->entitlements->subscription();
        $businessId = (int) $this->tenant->id();

        return view('billing.show', [
            'subscription' => $subscription,
            'tiers' => Plan::query()->tiers()->get(),
            'entitlements' => $this->entitlements,
            'usage' => [
                'shops' => $limits->count(Limit::Shops, $businessId),
                'staff' => $limits->count(Limit::Staff, $businessId),
                'products' => $limits->count(Limit::Products, $businessId),
            ],
            'balance' => $credits->balance($businessId),
            'creditsPerKes' => (float) config('ai.credits.per_kes', 10),
            'minTopUp' => (int) config('billing.ai_min_topup', 500),
            'payments' => PlatformPayment::query()->latest()->limit(10)->get(),
            'recentUsage' => AiUsageEvent::query()->where('kind', 'call')->latest()->limit(10)->get(),
            'onlinePayment' => $collector->isAvailable(),
        ]);
    }

    public function pay(Request $request, RequestPlatformPayment $payments): RedirectResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'months' => ['nullable', 'integer', 'min:1', 'max:12']]);

        try {
            $payments->execute(PlatformPayment::SUBSCRIPTION, $data['phone'], $request->user(), null, (int) ($data['months'] ?? 1));
        } catch (PaymentRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Check your phone and enter your M-Pesa PIN to complete the payment.'));
    }

    public function topUp(Request $request, RequestPlatformPayment $payments): RedirectResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'amount' => ['required', 'numeric', 'min:'.(int) config('billing.ai_min_topup', 500), 'max:1000000']]);

        try {
            $payments->execute(PlatformPayment::AI_TOPUP, $data['phone'], $request->user(), (float) $data['amount']);
        } catch (PaymentRejected $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Check your phone and enter your M-Pesa PIN to complete the top-up.'));
    }

    /**
     * Asks M-Pesa what became of a pending payment.
     */
    public function check(Request $request, PlatformPayment $payment, ConfirmPlatformPayment $confirm): RedirectResponse
    {
        $this->ownersOnly($request);

        $status = $confirm->execute($payment);

        return back()->with($status === PlatformPayment::CONFIRMED ? 'success' : 'error', match ($status) {
            PlatformPayment::CONFIRMED => __('Payment received. Thank you.'),
            PlatformPayment::FAILED => __('That payment did not go through.'),
            default => __('Still waiting for M-Pesa. Try again in a moment.'),
        });
    }

    /**
     * Choosing a tier during a trial (Phase 6): applies at once and keeps the
     * trial's end. When the choice would put the business over the new plan's
     * limits, nothing changes until the owner confirms the warning.
     */
    public function plan(Request $request, ChangeTrialPlan $change): RedirectResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate([
            'plan' => ['required', 'string', Rule::exists('plans', 'key')->where('is_active', true)],
            'shop_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'confirm' => ['nullable', 'boolean'],
        ]);

        $subscription = $this->entitlements->subscription();
        abort_if($subscription === null, 404);

        $plan = Plan::query()->where('key', $data['plan'])->firstOrFail();
        $shopCount = (int) ($data['shop_count'] ?? $subscription->shop_count);

        $warnings = $change->warnings($subscription, $plan, $shopCount);

        if ($warnings !== [] && ! $request->boolean('confirm')) {
            return back()->withInput()->with('plan_warnings', $warnings);
        }

        try {
            $change->execute($plan, $shopCount);
        } catch (PaymentRejected $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('You are now on :plan. Your trial still ends on :date.', [
            'plan' => $plan->name, 'date' => $subscription->trial_ends_at->toFormattedDayDateString(),
        ]));
    }

    /**
     * The number of shops the subscription pays for. Raising it takes effect
     * now and is billed from the next payment; lowering it below the shops the
     * business has makes every shop read-only until enough are deleted.
     */
    public function shops(Request $request): RedirectResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['shop_count' => ['required', 'integer', 'min:1', 'max:200']]);
        $subscription = $this->entitlements->subscription();

        abort_if($subscription === null, 404);

        if ($subscription->plan->is_trial_plan) {
            return back()->with('error', __('The Free Trial includes one shop. Choose a plan to add more.'));
        }

        $subscription->update(['shop_count' => $data['shop_count']]);

        $actual = app(PlanLimits::class)->count(Limit::Shops, (int) $this->tenant->id());

        return back()->with($actual > $data['shop_count'] ? 'error' : 'success', $actual > $data['shop_count']
            ? __('You have :actual shops but pay for :paid. Every shop is read-only until you delete :extra or pay for more.', [
                'actual' => $actual, 'paid' => $data['shop_count'], 'extra' => $actual - $data['shop_count'],
            ])
            : __('Now paying for :count shop(s).', ['count' => $data['shop_count']]));
    }

    private function ownersOnly(Request $request): void
    {
        abort_unless($request->user()->isBusinessOwner(), 403);
    }
}
