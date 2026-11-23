<?php

namespace App\Support\Pricing;

use App\Casts\Quantity;
use App\Enums\PricingType;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\ProductUnit;
use App\Models\Shop;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What every line of a sale should cost (stage 11b, step 1): the unit's price (UnitPrice), then the best quantity-break
 * rule that applies, as a price per base unit times the unit's factor, then the lower of the two. A rule never raises a
 * price.
 *
 * Quantity breaks count base units across all the sale's lines of a variation: "24 or more" is 24 bottles however they
 * were rung up, as one crate, bottles, or both on separate lines. A rule scoped to one unit counts only that unit's
 * lines; a rule scoped to one variation counts only that variation's.
 *
 * Money is bcmath throughout (no float rounding); the result is a money string to the penny.
 */
final class PriceResolver
{
    /**
     * The expected price of each line, in the order given.
     *
     * @param  list<array{product_id: int, variation_id: int, unit: ProductUnit, base_quantity: string}>  $lines
     * @return list<array{unit_price: ?string, source: ?string, base_price: ?string, rule_id: ?int}> unit_price is null when
     *                                                                                               the unit has no price at all (nothing to check against)
     */
    public function quote(Shop $shop, ?Customer $customer, array $lines, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $rules = $this->candidateRules($lines, $customer, $at);

        $quotes = [];
        foreach ($lines as $index => $line) {
            $base = UnitPrice::exact($shop, $line['unit'], $customer);
            $best = $this->bestRule($lines, $rules, $line);

            $basePrice = $base['price'] ?? null;
            $rulePrice = $best === null ? null : $best['price'];

            if ($rulePrice !== null && ($basePrice === null || bccomp($rulePrice, $basePrice, 2) < 0)) {
                $quotes[] = ['unit_price' => $rulePrice, 'source' => 'rule', 'base_price' => $basePrice, 'rule_id' => $best['rule']->id];

                continue;
            }

            $quotes[] = ['unit_price' => $basePrice, 'source' => $base['source'] ?? null, 'base_price' => $basePrice, 'rule_id' => null];
        }

        return $quotes;
    }

    /**
     * The rules that could apply to these lines: active, in their dates at $at, for the customer they name (or none).
     *
     * @param  list<array{product_id: int, variation_id: int, unit: ProductUnit, base_quantity: string}>  $lines
     * @return Collection<int, PricingRule>
     */
    private function candidateRules(array $lines, ?Customer $customer, CarbonInterface $at)
    {
        $productIds = collect($lines)->pluck('product_id')->unique()->values();

        return PricingRule::query()
            ->whereIn('product_id', $productIds)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('start_date')->orWhere('start_date', '<=', $at))
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $at))
            ->where(fn ($query) => $query->where('type', '!=', PricingType::CUSTOMER_SPECIFIC->value)
                ->orWhere('customer_id', $customer?->id ?? 0))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    /**
     * The applicable rule that gives line $index the lowest price, with that price, or null when none applies.
     *
     * @param  list<array{product_id: int, variation_id: int, unit: ProductUnit, base_quantity: string}>  $lines
     * @param  Collection<int, PricingRule>  $rules
     * @return array{rule: PricingRule, price: string}|null
     */
    private function bestRule(array $lines, $rules, array $line): ?array
    {
        $best = null;

        foreach ($rules as $rule) {
            if ((int) $rule->product_id !== (int) $line['product_id']) {
                continue;
            }
            if ($rule->variation_id !== null && (int) $rule->variation_id !== (int) $line['variation_id']) {
                continue;
            }
            if ($rule->unit_id !== null && (int) $rule->unit_id !== (int) $line['unit']->id) {
                continue;
            }
            if (! $this->quantityInRange($rule, $this->scopedBaseQuantity($rule, $lines))) {
                continue;
            }

            $price = bcmul($this->ruleUnitPrice($rule), Quantity::normalize($line['unit']->factor), 6);
            $price = UnitPrice::money($price);

            // Ties keep the earlier rule in the list (higher priority first), so the choice is stable.
            if ($best === null || bccomp($price, $best['price'], 2) < 0) {
                $best = ['rule' => $rule, 'price' => $price];
            }
        }

        return $best;
    }

    /**
     * The base quantity of the sale's lines that a rule's thresholds count: the same product, and the rule's variation
     * and unit when it names them.
     *
     * @param  list<array{product_id: int, variation_id: int, unit: ProductUnit, base_quantity: string}>  $lines
     */
    private function scopedBaseQuantity(PricingRule $rule, array $lines): string
    {
        $total = '0';

        foreach ($lines as $line) {
            if ((int) $line['product_id'] !== (int) $rule->product_id) {
                continue;
            }
            if ($rule->variation_id !== null && (int) $line['variation_id'] !== (int) $rule->variation_id) {
                continue;
            }
            if ($rule->unit_id !== null && (int) $line['unit']->id !== (int) $rule->unit_id) {
                continue;
            }

            $total = bcadd($total, Quantity::normalize($line['base_quantity']), 3);
        }

        return $total;
    }

    /**
     * A rule's thresholds are whole base units: a minimum (none, or 0, means any) and a maximum (none means no cap).
     */
    private function quantityInRange(PricingRule $rule, string $quantity): bool
    {
        if ($rule->min_quantity !== null && $rule->min_quantity > 0 && bccomp($quantity, (string) $rule->min_quantity, 3) < 0) {
            return false;
        }

        return $rule->max_quantity === null || bccomp($quantity, (string) $rule->max_quantity, 3) <= 0;
    }

    /**
     * A rule's price per base unit after its percentage and amount discounts, never below zero.
     */
    private function ruleUnitPrice(PricingRule $rule): string
    {
        $price = (string) ($rule->price ?? '0');

        if ($rule->discount_percentage !== null && (string) $rule->discount_percentage !== '0.00') {
            $price = bcsub($price, bcmul($price, bcdiv((string) $rule->discount_percentage, '100', 6), 6), 6);
        }

        if ($rule->discount_amount !== null && (string) $rule->discount_amount !== '0.00') {
            $price = bcsub($price, (string) $rule->discount_amount, 6);
        }

        return bccomp($price, '0', 6) < 0 ? '0.000000' : $price;
    }
}
