<?php

namespace App\Contracts;

use App\Models\PlatformPayment;
use App\Services\Billing\CollectionResult;

/**
 * Collects money for the platform through the private M-Pesa package (Phase
 * 4, decided 12 October 2026): the package acts as an API; an STK push is
 * started with the platform's own account details, and a payment is believed
 * only after the API itself says so when queried, never because a callback
 * claimed it.
 *
 * Until go-live (about 12 November 2026) the null collector is bound and
 * payments are recorded by hand; `config('billing.mpesa_enabled')` is the
 * switch.
 */
interface PaymentCollector
{
    public function isAvailable(): bool;

    /**
     * Starts an STK push for $payment to $phone. $reference is our account
     * reference (the payment's uuid) that the API echoes back. Returns the
     * checkout request id, the key payments are matched on.
     */
    public function request(PlatformPayment $payment, string $phone, string $reference): string;

    /**
     * Asks the API what became of $payment's checkout request.
     */
    public function confirm(PlatformPayment $payment): CollectionResult;
}
