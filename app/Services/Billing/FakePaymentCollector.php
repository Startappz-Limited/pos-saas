<?php

namespace App\Services\Billing;

use App\Contracts\PaymentCollector;
use App\Models\PlatformPayment;

/**
 * For tests: starts requests, and answers confirmations with whatever it was
 * told to.
 */
class FakePaymentCollector implements PaymentCollector
{
    /** @var array<string, CollectionResult> */
    public array $answers = [];

    /** @var list<array{checkout: string, phone: string, reference: string}> */
    public array $requests = [];

    public function isAvailable(): bool
    {
        return true;
    }

    public function request(PlatformPayment $payment, string $phone, string $reference): string
    {
        $checkout = 'ws_CO_'.count($this->requests).'_'.substr(md5($reference), 0, 8);
        $this->requests[] = ['checkout' => $checkout, 'phone' => $phone, 'reference' => $reference];

        return $checkout;
    }

    public function answer(string $checkout, CollectionResult $result): self
    {
        $this->answers[$checkout] = $result;

        return $this;
    }

    public function confirm(PlatformPayment $payment): CollectionResult
    {
        return $this->answers[$payment->checkout_request_id] ?? new CollectionResult(false, error: 'pending');
    }
}
