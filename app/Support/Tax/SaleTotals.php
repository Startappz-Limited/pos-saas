<?php

namespace App\Support\Tax;

/**
 * Every monetary figure a sale needs, computed server-side.
 *
 * `lines` is keyed by the caller's own item index so a controller can write the
 * per-line tax columns while looping over the request payload.
 */
readonly class SaleTotals
{
    /**
     * @param  array<int|string, array<string, float|string|null>>  $lines
     * @param  array<int, array<string, mixed>>  $breakdown
     */
    public function __construct(
        public float $subtotal,
        public float $discountAmount,
        public float $taxAmount,
        public float $taxableAmount,
        public bool $taxInclusive,
        public float $deliveryFee,
        public float $packagingFee,
        public float $otherExpenses,
        public float $totalAmount,
        public float $totalCost,
        public float $totalProfit,
        public array $lines,
        public array $breakdown = [],
    ) {}

    public function fees(): float
    {
        return round($this->deliveryFee + $this->packagingFee + $this->otherExpenses, 2);
    }

    /**
     * @return array<string, float|null>
     */
    public function line(int|string $key): array
    {
        return $this->lines[$key] ?? [];
    }
}
