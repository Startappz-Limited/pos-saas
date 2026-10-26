<?php

namespace App\Services\Mpesa;

use Carbon\CarbonInterface;

/**
 * A payment as the M-Pesa API reports it when queried: the only source the
 * server believes (never a callback alone).
 */
final readonly class MpesaPayment
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public function __construct(
        public string $state,
        public ?string $receipt = null,
        public ?string $amount = null,
        public ?string $phone = null,
        public ?CarbonInterface $paidAt = null,
        public ?string $error = null,
    ) {}

    public static function paid(string $receipt, string $amount, ?string $phone = null, ?CarbonInterface $paidAt = null): self
    {
        return new self(self::PAID, $receipt, $amount, $phone, $paidAt);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function failed(string $error): self
    {
        return new self(self::FAILED, error: $error);
    }
}
