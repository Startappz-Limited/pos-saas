<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentCollector;
use App\Exceptions\PaymentRejected;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\PhoneNumber;

/**
 * Starts an M-Pesa payment to the platform (Phase 4): a pending payment, then
 * an STK push through the PaymentCollector with the payment's uuid as the
 * account reference. The payment is settled later by ConfirmPlatformPayment,
 * which asks the API. Refused while the automated path is off (before
 * go-live), when payments are recorded by hand.
 */
class RequestPlatformPayment
{
    public function __construct(private PaymentCollector $collector) {}

    /**
     * @param  float|null  $amount  for a top-up; a subscription payment is whole months
     *
     * @throws PaymentRejected
     */
    public function execute(string $purpose, string $phone, ?User $user, ?float $amount = null, int $months = 1): PlatformPayment
    {
        if (! $this->collector->isAvailable()) {
            throw new PaymentRejected(__('Online payment is not available yet. Pay the platform by M-Pesa as instructed and we will record it.'));
        }

        $msisdn = PhoneNumber::normalize($phone);
        if ($msisdn === null) {
            throw new PaymentRejected(__('Enter the M-Pesa phone number that will pay.'));
        }

        $amount = match ($purpose) {
            PlatformPayment::SUBSCRIPTION => $this->subscriptionAmount($months),
            PlatformPayment::AI_TOPUP => $this->topUpAmount((float) $amount),
            default => throw new PaymentRejected(__('Unknown payment purpose.')),
        };

        $payment = PlatformPayment::create([
            'purpose' => $purpose,
            'amount' => $amount,
            'currency' => config('billing.currency', 'KES'),
            'method' => PlatformPayment::MPESA,
            'status' => PlatformPayment::PENDING,
            'phone' => $msisdn,
            'recorded_by' => $user?->id,
        ]);

        $payment->update(['checkout_request_id' => $this->collector->request($payment, $msisdn, (string) $payment->uuid)]);

        return $payment;
    }

    private function subscriptionAmount(int $months): float
    {
        $subscription = Subscription::query()->first() ?? throw new PaymentRejected(__('This business has no subscription.'));

        return $subscription->monthlyTotal() * max(1, $months);
    }

    private function topUpAmount(float $amount): float
    {
        $min = (float) config('billing.ai_min_topup', 500);

        if ($amount < $min) {
            throw new PaymentRejected(__('The minimum AI top-up is KES :min.', ['min' => number_format($min)]));
        }

        return round($amount, 2);
    }
}
