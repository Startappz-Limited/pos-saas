<?php

namespace App\Services;

use App\Enums\TaxClass;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Support\Tax\TaxLine;
use App\Support\Tax\TaxResult;

/**
 * Computes VAT for a sale, server-side.
 *
 * Before this existed the sale controllers stored whatever `tax_amount` the
 * client posted, so the tax on an invoice was whatever the cashier (or the
 * mobile app) typed. VAT is a statutory calculation and the till is the system
 * of record for it, so the amount is now derived from the lines.
 *
 * The engine only engages for a shop flagged `vat_registered`; an unregistered
 * business may not charge VAT, and every shop starts unregistered so that
 * enabling this changes nothing until someone opts a shop in.
 *
 * Two pricing conventions are supported, because both are common in Kenya:
 *
 *   inclusive (retail)    the shelf price is what the customer pays and the VAT
 *                         is extracted from it: tax = amount × r / (100 + r)
 *   exclusive (wholesale) the quoted price is net and VAT is added on top:
 *                         tax = amount × r / 100
 */
class TaxService
{
    /**
     * Whether VAT should be computed for this shop at all.
     */
    public function enabledFor(?Shop $shop): bool
    {
        if (! config('tax.enabled', true)) {
            return false;
        }

        return (bool) $shop?->vat_registered;
    }

    /**
     * Whether the shop's quoted prices already contain VAT.
     */
    public function pricesIncludeTax(?Shop $shop): bool
    {
        $override = $this->shopSetting($shop, 'prices_include_tax');

        if ($override !== null) {
            return (bool) $override;
        }

        return (bool) config('tax.prices_include_tax', true);
    }

    /**
     * The tax class applied to products that do not specify one.
     */
    public function defaultClass(?Shop $shop): TaxClass
    {
        $override = $this->shopSetting($shop, 'default_class');

        return TaxClass::tryFrom((string) $override)
            ?? TaxClass::tryFrom((string) config('tax.default_class'))
            ?? TaxClass::Standard;
    }

    /**
     * The percentage rate for a class, honouring any per-shop override.
     */
    public function rateFor(TaxClass $class, ?Shop $shop = null): float
    {
        $overrides = $this->shopSetting($shop, 'rates');

        if (is_array($overrides) && array_key_exists($class->value, $overrides)) {
            return round((float) $overrides[$class->value], 3);
        }

        return round((float) config("tax.rates.{$class->value}", 0), 3);
    }

    /**
     * Resolve the tax class for a product line.
     *
     * A variation may override its parent product; either may be left blank to
     * inherit the shop default.
     */
    public function classForProduct(
        Product|ProductVariation|null $product,
        ?Shop $shop = null,
        ?ProductVariation $variation = null,
    ): TaxClass {
        $candidates = [
            $variation?->tax_class,
            $product?->tax_class,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate instanceof TaxClass) {
                return $candidate;
            }

            $resolved = TaxClass::tryFrom((string) $candidate);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $this->defaultClass($shop);
    }

    /**
     * Split a single amount into its net and VAT parts.
     *
     * @return array{taxable: float, tax: float}
     */
    public function split(float $amount, TaxClass $class, bool $inclusive, ?Shop $shop = null): array
    {
        $rate = $this->rateFor($class, $shop);

        if ($rate <= 0.0 || $amount == 0.0) {
            return ['taxable' => round($amount, 2), 'tax' => 0.0];
        }

        if ($inclusive) {
            $tax = round($amount * $rate / (100 + $rate), 2);

            return ['taxable' => round($amount - $tax, 2), 'tax' => $tax];
        }

        return ['taxable' => round($amount, 2), 'tax' => round($amount * $rate / 100, 2)];
    }

    /**
     * Compute the VAT position of a whole sale.
     *
     * Sale-level discounts are apportioned across the lines before VAT is worked
     * out, because VAT is chargeable on the discounted consideration — not on the
     * list price. The apportionment is pro-rata by line value, with the rounding
     * residue pushed onto the last line so the shares always sum to the discount.
     *
     * @param  array<int|string, array{amount: float, tax_class?: TaxClass|string|null}>  $lines
     * @param  float  $discount  Sale-level discount, in the same convention as the line amounts.
     * @param  float  $fees  Delivery/packaging/other charges recharged to the customer.
     */
    public function calculate(
        array $lines,
        float $discount = 0.0,
        float $fees = 0.0,
        ?Shop $shop = null,
        ?bool $inclusive = null,
    ): TaxResult {
        $inclusive ??= $this->pricesIncludeTax($shop);

        if ($lines === []) {
            return TaxResult::none($inclusive);
        }

        $shares = $this->apportion(array_map(
            static fn (array $line): float => (float) ($line['amount'] ?? 0),
            $lines,
        ), $discount);

        $taxLines = [];
        $totalTax = 0.0;
        $totalTaxable = 0.0;

        foreach ($lines as $key => $line) {
            $class = $this->resolveClass($line['tax_class'] ?? null, $shop);
            $charged = round((float) ($line['amount'] ?? 0) - $shares[$key], 2);

            $split = $this->split($charged, $class, $inclusive, $shop);

            $taxLines[$key] = new TaxLine(
                key: $key,
                taxClass: $class,
                rate: $this->rateFor($class, $shop),
                chargedAmount: $charged,
                taxableAmount: $split['taxable'],
                taxAmount: $split['tax'],
                discountShare: $shares[$key],
            );

            $totalTax += $split['tax'];
            $totalTaxable += $split['taxable'];
        }

        $feeTax = 0.0;

        if ($fees > 0.0 && config('tax.fees_taxable', false)) {
            $feeClass = $this->resolveClass(config('tax.fees_tax_class'), $shop);
            $feeSplit = $this->split($fees, $feeClass, $inclusive, $shop);

            $feeTax = $feeSplit['tax'];
            $totalTax += $feeTax;
            $totalTaxable += $feeSplit['taxable'];

            $taxLines['__fees'] = new TaxLine(
                key: '__fees',
                taxClass: $feeClass,
                rate: $this->rateFor($feeClass, $shop),
                chargedAmount: round($fees, 2),
                taxableAmount: $feeSplit['taxable'],
                taxAmount: $feeTax,
            );
        }

        return new TaxResult(
            lines: $taxLines,
            taxAmount: round($totalTax, 2),
            taxableAmount: round($totalTaxable, 2),
            inclusive: $inclusive,
            feeTaxAmount: round($feeTax, 2),
        );
    }

    private function resolveClass(TaxClass|string|null $class, ?Shop $shop): TaxClass
    {
        if ($class instanceof TaxClass) {
            return $class;
        }

        return TaxClass::tryFrom((string) $class) ?? $this->defaultClass($shop);
    }

    /**
     * Spread a sale-level discount across lines pro-rata by value.
     *
     * @param  array<int|string, float>  $amounts
     * @return array<int|string, float>
     */
    private function apportion(array $amounts, float $discount): array
    {
        $shares = array_map(static fn (): float => 0.0, $amounts);

        if ($discount <= 0.0) {
            return $shares;
        }

        $total = array_sum($amounts);

        if ($total <= 0.0) {
            return $shares;
        }

        // A discount larger than the sale would invert the line totals; cap it.
        $discount = min($discount, $total);

        $keys = array_keys($amounts);
        $lastKey = array_key_last($keys);
        $allocated = 0.0;

        foreach ($keys as $position => $key) {
            if ($position === $lastKey) {
                // The final line absorbs the rounding residue so the shares sum
                // exactly to the discount.
                $shares[$key] = round($discount - $allocated, 2);

                continue;
            }

            $share = round($discount * ($amounts[$key] / $total), 2);
            $shares[$key] = $share;
            $allocated += $share;
        }

        return $shares;
    }

    /**
     * Read a per-shop override out of `Shop.settings['tax']`.
     */
    private function shopSetting(?Shop $shop, string $key): mixed
    {
        if (! $shop) {
            return null;
        }

        $settings = $shop->settings;

        if (! is_array($settings)) {
            return null;
        }

        return data_get($settings, "tax.{$key}");
    }
}
