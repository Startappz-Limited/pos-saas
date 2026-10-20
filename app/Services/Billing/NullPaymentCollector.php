<?php

namespace App\Services\Billing;

use App\Contracts\PaymentCollector;
use App\Models\PlatformPayment;
use RuntimeException;

/**
 * Bound until the platform goes live on M-Pesa: nothing can be collected
 * automatically, and payments are recorded by hand.
 */
class NullPaymentCollector implements PaymentCollector
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function request(PlatformPayment $payment, string $phone, string $reference): string
    {
        throw new RuntimeException(__('Online payment is not available yet.'));
    }

    public function confirm(PlatformPayment $payment): CollectionResult
    {
        return new CollectionResult(false, error: __('Online payment is not available yet.'));
    }
}
