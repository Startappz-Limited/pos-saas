<?php

namespace App\Support\Tax;

use App\Enums\TaxClass;

/**
 * The computed VAT position of a whole sale.
 *
 * `breakdown` is grouped by tax class because the VAT return reports each class
 * separately — zero-rated and exempt supplies both attract no VAT but are not
 * interchangeable on the return.
 */
readonly class TaxResult
{
    /**
     * @param  array<int|string, TaxLine>  $lines
     */
    public function __construct(
        public array $lines,
        public float $taxAmount,
        public float $taxableAmount,
        public bool $inclusive,
        public float $feeTaxAmount = 0.0,
    ) {}

    public static function none(bool $inclusive = false): self
    {
        return new self([], 0.0, 0.0, $inclusive);
    }

    public function lineFor(int|string $key): ?TaxLine
    {
        return $this->lines[$key] ?? null;
    }

    /**
     * Totals grouped by tax class, ready for the invoice and the VAT return.
     *
     * @return array<string, array{tax_class: string, label: string, kra_code: string, rate: float, taxable_amount: float, tax_amount: float}>
     */
    public function breakdown(): array
    {
        $breakdown = [];

        foreach ($this->lines as $line) {
            $key = $line->taxClass->value;

            if (! isset($breakdown[$key])) {
                $breakdown[$key] = [
                    'tax_class' => $key,
                    'label' => $line->taxClass->label(),
                    'kra_code' => $line->taxClass->kraCode(),
                    'rate' => $line->rate,
                    'taxable_amount' => 0.0,
                    'tax_amount' => 0.0,
                ];
            }

            $breakdown[$key]['taxable_amount'] = round($breakdown[$key]['taxable_amount'] + $line->taxableAmount, 2);
            $breakdown[$key]['tax_amount'] = round($breakdown[$key]['tax_amount'] + $line->taxAmount, 2);
        }

        return $breakdown;
    }

    /**
     * Net turnover that counts as a taxable supply (standard, reduced, zero rated).
     */
    public function taxableSupplyAmount(): float
    {
        $total = 0.0;

        foreach ($this->lines as $line) {
            if ($line->taxClass->isTaxableSupply()) {
                $total += $line->taxableAmount;
            }
        }

        return round($total, 2);
    }

    /**
     * Net turnover outside the scope of VAT (exempt, non-VAT).
     */
    public function exemptSupplyAmount(): float
    {
        $total = 0.0;

        foreach ($this->lines as $line) {
            if (! $line->taxClass->isTaxableSupply()) {
                $total += $line->taxableAmount;
            }
        }

        return round($total, 2);
    }

    public function hasTax(): bool
    {
        return $this->taxAmount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inclusive' => $this->inclusive,
            'tax_amount' => $this->taxAmount,
            'taxable_amount' => $this->taxableAmount,
            'fee_tax_amount' => $this->feeTaxAmount,
            'breakdown' => array_values($this->breakdown()),
        ];
    }

    /**
     * Convenience for callers that only need the rate applied to one class.
     */
    public function rateFor(TaxClass $class): float
    {
        foreach ($this->lines as $line) {
            if ($line->taxClass === $class) {
                return $line->rate;
            }
        }

        return 0.0;
    }
}
