<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Billing\ConfirmPlatformPayment;
use App\Actions\Billing\RequestPlatformPayment;
use App\Contracts\PaymentCollector;
use App\Enums\Limit;
use App\Exceptions\PaymentRejected;
use App\Http\Controllers\Controller;
use App\Models\PlatformPayment;
use App\Services\Billing\AiCredits;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanLimits;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Billing on `/api/v1` (Phase 4): the same flows as the billing page, for
 * owners. `{success, message, data}`; refusals are 403, a payment that can't be
 * started is 422. Reachable while the business is read-only.
 */
class BillingController extends Controller
{
    public function __construct(private TenantContext $tenant, private Entitlements $entitlements) {}

    public function show(Request $request, AiCredits $credits, PlanLimits $limits, PaymentCollector $collector): JsonResponse
    {
        $this->ownersOnly($request);

        $subscription = $this->entitlements->subscription();
        $businessId = (int) $this->tenant->id();

        return $this->ok([
            'plan' => $subscription?->plan->key,
            'status' => match (true) {
                $subscription === null => 'unmetered',
                $subscription->isInGoodStanding() => $subscription->status,
                default => 'lapsed',
            },
            'ends_at' => $subscription?->endsAt()?->toIso8601String(),
            'grace_ends_at' => $this->entitlements->graceEndsAt()?->toIso8601String(),
            'read_only_reason' => $this->entitlements->readOnlyReason(),
            'shops_paid' => $subscription?->shop_count,
            'shops_used' => $limits->count(Limit::Shops, $businessId),
            'monthly_total' => $subscription?->monthlyTotal(),
            'ai_credits' => $credits->balance($businessId),
            'ai_min_topup' => (int) config('billing.ai_min_topup', 500),
            'online_payment' => $collector->isAvailable(),
        ]);
    }

    public function pay(Request $request, RequestPlatformPayment $payments): JsonResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'months' => ['nullable', 'integer', 'min:1', 'max:12']]);

        return $this->start(fn () => $payments->execute(PlatformPayment::SUBSCRIPTION, $data['phone'], $request->user(), null, (int) ($data['months'] ?? 1)));
    }

    public function topUp(Request $request, RequestPlatformPayment $payments): JsonResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['phone' => ['required', 'string', 'max:20'], 'amount' => ['required', 'numeric', 'min:'.(int) config('billing.ai_min_topup', 500), 'max:1000000']]);

        return $this->start(fn () => $payments->execute(PlatformPayment::AI_TOPUP, $data['phone'], $request->user(), (float) $data['amount']));
    }

    public function check(Request $request, PlatformPayment $payment, ConfirmPlatformPayment $confirm): JsonResponse
    {
        $this->ownersOnly($request);

        return $this->ok(['payment' => $payment->uuid, 'status' => $confirm->execute($payment)]);
    }

    public function shops(Request $request): JsonResponse
    {
        $this->ownersOnly($request);

        $data = $request->validate(['shop_count' => ['required', 'integer', 'min:1', 'max:200']]);
        $subscription = $this->entitlements->subscription();

        abort_if($subscription === null, 404);

        $subscription->update(['shop_count' => $data['shop_count']]);

        return $this->ok(['shops_paid' => (int) $data['shop_count'], 'read_only_reason' => $this->entitlements->readOnlyReason()]);
    }

    private function start(callable $start): JsonResponse
    {
        try {
            $payment = $start();
        } catch (PaymentRejected $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['payment' => [$e->getMessage()]]], 422);
        }

        return $this->ok(['payment' => $payment->uuid, 'status' => $payment->status], __('Check your phone and enter your M-Pesa PIN.'));
    }

    private function ownersOnly(Request $request): void
    {
        abort_unless($request->user()->isBusinessOwner(), 403);
    }

    private function ok(mixed $data, ?string $message = null): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data]);
    }
}
