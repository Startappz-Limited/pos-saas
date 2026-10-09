<?php

namespace App\Support\Tax;

use App\Enums\TaxClass;

/**
 * The VAT position of one sale line, after any sale-level discount has been
 * apportioned onto it.
 */
readonly class TaxLine
{
    public function __construct(
        /** Index of the line in the caller's own array, so results can be mapped back. */
        public int|string $key,
        public TaxClass $taxClass,
        /** Rate as a percentage, e.g. 16.0. */
        public float $rate,
        /** What the customer is charged for this line: VAT-inclusive under inclusive pricing. */
        public float $chargedAmount,
        /** The line net of VAT. */
        public float $taxableAmount,
        public float $taxAmount,
        /** This line's share of the sale-level discount. */
        public float $discountShare = 0.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'tax_class' => $this->taxClass->value,
            'tax_rate' => $this->rate,
            'charged_amount' => $this->chargedAmount,
            'taxable_amount' => $this->taxableAmount,
            'tax_amount' => $this->taxAmount,
            'discount_share' => $this->discountShare,
        ];
    }
}
