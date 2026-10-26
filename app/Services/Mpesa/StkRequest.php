<?php

namespace App\Services\Mpesa;

/**
 * What the M-Pesa API said when asked to send an STK Push: the checkout
 * request id payments are matched on, or why it was refused.
 */
final readonly class StkRequest
{
    public function __construct(
        public bool $accepted,
        public ?string $checkoutRequestId = null,
        public ?string $merchantRequestId = null,
        public ?string $error = null,
    ) {}

    public static function sent(string $checkoutRequestId, ?string $merchantRequestId = null): self
    {
        return new self(true, $checkoutRequestId, $merchantRequestId);
    }

    public static function refused(string $error): self
    {
        return new self(false, error: $error);
    }
}
