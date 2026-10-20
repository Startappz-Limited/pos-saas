<?php

namespace App\Actions\Billing;

use App\Contracts\PaymentCollector;
use App\Models\PlatformPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Settles a pending M-Pesa payment by asking the API (Phase 4). A callback only
 * says "look at this checkout request"; what it claims is never believed. The
 * API's answer must be a paid request for at least the amount we asked and
 * carry our account reference (the payment's uuid), so a payment can't be
 * applied to the wrong business; the M-Pesa receipt number is unique, so one
 * receipt can't pay twice. Safe to run again: a confirmed payment is applied
 * once.
 */
class ConfirmPlatformPayment
{
    public function __construct(
        private PaymentCollector $collector,
        private ApplyPlatformPayment $apply,
        private TenantContext $tenant,
    ) {}

    /**
     * @return string the payment's status afterwards
     */
    public function execute(PlatformPayment $payment): string
    {
        return $this->tenant->run($payment->business_id, fn (): string => $this->settle($payment));
    }

    private function settle(PlatformPayment $payment): string
    {
        if ($payment->status === PlatformPayment::CONFIRMED) {
            $this->apply->execute($payment);

            return $payment->status;
        }

        if ($payment->status !== PlatformPayment::PENDING || blank($payment->checkout_request_id)) {
            return $payment->status;
        }

        $result = $this->collector->confirm($payment);

        if (! $result->paid) {
            if ($result->error !== null && $result->error !== 'pending') {
                $payment->update(['status' => PlatformPayment::FAILED, 'raw' => $result->raw ?: ['error' => $result->error]]);
            }

            return $payment->status;
        }

        $matches = $result->reference === (string) $payment->uuid
            && $result->receipt !== null
            && $result->amount !== null
            && $result->amount + 0.005 >= (float) $payment->amount;

        if (! $matches) {
            $payment->update(['status' => PlatformPayment::FAILED, 'raw' => ['mismatch' => true, ...$result->raw]]);

            return $payment->status;
        }

        try {
            $payment->update([
                'status' => PlatformPayment::CONFIRMED,
                'mpesa_receipt' => $result->receipt,
                'raw' => $result->raw,
                'confirmed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same M-Pesa receipt already paid for something else.
            $payment->update(['status' => PlatformPayment::FAILED, 'raw' => ['duplicate_receipt' => true]]);

            return $payment->status;
        }

        $this->apply->execute($payment);

        return $payment->status;
    }
}
