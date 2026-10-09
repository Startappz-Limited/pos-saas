<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Services\TaxService;
use App\Support\Tax\SaleTotals;
use Illuminate\Support\Collection;

/**
 * Works out the money on a sale: subtotal, VAT, cost, profit and the payable total.
 *
 * Both the web POS and the API create sales, and they used to duplicate this
 * arithmetic — including the same two bugs. It lives here so the two paths
 * cannot drift, which the project requires of every web/API pair.
 *
 * Two things changed relative to the inline version this replaces:
 *
 *  1. VAT is derived from the lines instead of trusted from the request, for any
 *     shop flagged VAT registered. Shops that are not registered keep the legacy
 *     behaviour of storing the posted `tax_amount` verbatim.
 *
 *  2. Profit excludes VAT. It used to be `total_amount - total_cost`, and
 *     `total_amount` contains the tax, so every sale booked the VAT owed to KRA
 *     as if it were margin.
 */
class CalculateSaleTotals
{
    public function __construct(private readonly TaxService $tax) {}

    /**
     * @param  array<int, array<string, mixed>>  $items  Validated request items.
     * @param  Collection<int, Product>  $productsById
     * @param  Collection<int, ProductVariation>  $variationsById
     * @param  array<string, mixed>  $options  discount_amount, tax_amount, delivery_fee,
     *                                         packaging_fee, other_expenses
     */
    public function handle(
        array $items,
        Collection $productsById,
        Collection $variationsById,
        ?Shop $shop,
        array $options = [],
    ): SaleTotals {
        $discount = round((float) ($options['discount_amount'] ?? 0), 2);
        $deliveryFee = round((float) ($options['delivery_fee'] ?? 0), 2);
        $packagingFee = round((float) ($options['packaging_fee'] ?? 0), 2);
        $otherExpenses = round((float) ($options['other_expenses'] ?? 0), 2);
        $fees = round($deliveryFee + $packagingFee + $otherExpenses, 2);

        $subtotal = 0.0;
        $totalCost = 0.0;
        $taxInputs = [];
        $lineFacts = [];

        foreach ($items as $index => $item) {
            $product = $productsById[$item['product_id']] ?? null;
            $variation = isset($item['variation_id'])
                ? ($variationsById[$item['variation_id']] ?? null)
                : null;

            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['price'];

            // A purchase cost may legitimately be unknown (NULL) for imported
            // products while sale_items.unit_cost is NOT NULL, so an unknown cost
            // records zero COGS until `sales:recompute-profit` is run.
            $unitCost = (float) (($variation ? $variation->cost_price : $product?->cost_price) ?? 0);

            $lineTotal = round($quantity * $unitPrice, 2);
            $lineCost = round($quantity * $unitCost, 2);

            $subtotal += $lineTotal;
            $totalCost += $lineCost;

            $taxInputs[$index] = [
                'amount' => $lineTotal,
                'tax_class' => $this->tax->classForProduct($product, $shop, $variation),
            ];

            $lineFacts[$index] = [
                'line_total' => $lineTotal,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
            ];
        }

        $subtotal = round($subtotal, 2);
        $totalCost = round($totalCost, 2);

        $engineOn = $this->tax->enabledFor($shop);

        if ($engineOn) {
            $inclusive = $this->tax->pricesIncludeTax($shop);
            $result = $this->tax->calculate($taxInputs, $discount, $fees, $shop, $inclusive);

            $taxAmount = $result->taxAmount;
            $taxableAmount = $result->taxableAmount;
            $breakdown = array_values($result->breakdown());

            // Under inclusive pricing the VAT is already contained in the line
            // prices, so adding it again would double-charge the customer.
            $totalAmount = $inclusive
                ? round($subtotal - $discount + $fees, 2)
                : round($subtotal - $discount + $taxAmount + $fees, 2);
        } else {
            // Legacy path, byte-for-byte the previous behaviour: the client's
            // figure is stored as-is and added on top.
            $inclusive = false;
            $result = null;
            $breakdown = [];
            $taxAmount = round((float) ($options['tax_amount'] ?? 0), 2);
            $taxableAmount = round($subtotal - $discount, 2);
            $totalAmount = round($subtotal - $discount + $taxAmount + $fees, 2);
        }

        $lines = [];

        foreach ($lineFacts as $index => $facts) {
            $taxLine = $result?->lineFor($index);

            // Net of both VAT and this line's share of the sale-level discount.
            $lineTaxable = $taxLine?->taxableAmount ?? round($facts['line_total'] - $this->legacyDiscountShare($facts['line_total'], $subtotal, $discount), 2);
            $lineTax = $taxLine?->taxAmount ?? 0.0;

            $profit = round($lineTaxable - $facts['total_cost'], 2);

            $lines[$index] = [
                'line_total' => $facts['line_total'],
                'unit_cost' => $facts['unit_cost'],
                'total_cost' => $facts['total_cost'],
                'tax_class' => $engineOn ? $taxLine?->taxClass->value : null,
                'tax_rate' => $taxLine?->rate ?? 0.0,
                'tax_amount' => $lineTax,
                'taxable_amount' => $lineTaxable,
                'profit' => $profit,
                'profit_margin' => $lineTaxable > 0 ? round($profit / $lineTaxable * 100, 2) : 0.0,
            ];
        }

        return new SaleTotals(
            subtotal: $subtotal,
            discountAmount: $discount,
            taxAmount: $taxAmount,
            taxableAmount: $taxableAmount,
            taxInclusive: $inclusive,
            deliveryFee: $deliveryFee,
            packagingFee: $packagingFee,
            otherExpenses: $otherExpenses,
            totalAmount: $totalAmount,
            totalCost: $totalCost,
            // VAT is collected on the state's behalf, never margin. Fees are
            // recharged to the customer and so do count towards it.
            totalProfit: round($totalAmount - $taxAmount - $totalCost, 2),
            lines: $lines,
            breakdown: $breakdown,
        );
    }

    /**
     * Pro-rata share of a sale-level discount, for the no-VAT path.
     */
    private function legacyDiscountShare(float $lineTotal, float $subtotal, float $discount): float
    {
        if ($discount <= 0.0 || $subtotal <= 0.0) {
            return 0.0;
        }

        return round(min($discount, $subtotal) * ($lineTotal / $subtotal), 2);
    }
}
