<?php

namespace App\Actions\Sale;

use App\Casts\Quantity;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use App\Support\Pricing\PriceResolver;
use App\Support\Pricing\UnitPrice;
use App\Support\Stock\SaleLineUnit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Checks what a sale charges against what the server expects (stage 11b, steps 3 and 4).
 *
 * Each line: the charged total (price x quantity, to the cent) against the expected total (the quote's unit price x
 * quantity, to the cent). A line is short only when it is more than one cent under, so a cent per line is tolerated.
 * The sale's discount: within the seller's role limit, or approved by someone who holds `sales.override-price`, it is
 * allowed; anything beyond that is a violation.
 *
 * A short line, or a discount above the limit, needs an approver who holds `sales.override-price`. Online (the web POS
 * and the flat API) the seller approves by default, and a refusal is returned. In a push (`$online` false) nobody is
 * assumed: only the approver a till names counts, so an approval an app didn't send is a mismatch, recorded and reviewed,
 * never refused.
 *
 * Read-only: it changes nothing. RecordSale and the web POS act on the result.
 */
final class CheckSalePrices
{
    public function __construct(private readonly PriceResolver $resolver) {}

    /**
     * @param  list<array<string, mixed>>  $items  validated sale lines, as StoreSaleRequest validates them
     * @param  array<string, mixed>  $sale  the sale's validated fields (`discount_amount`, `discount_approved_by`)
     */
    public function execute(Shop $shop, ?Customer $customer, array $items, array $sale, ?User $seller, CarbonInterface $at, bool $online): PriceAssessment
    {
        $productsById = Product::query()->whereIn('id', collect($items)->pluck('product_id')->unique())->get()->keyBy('id');
        $variationsById = ProductVariation::query()->withoutGlobalScope(ProductVariation::HIDE_DEFAULT)
            ->whereIn('id', collect($items)->pluck('variation_id')->filter()->unique())->get()->keyBy('id');

        $prepared = [];
        foreach ($items as $index => $item) {
            $product = $productsById[$item['product_id']];
            $variation = isset($item['variation_id']) ? $variationsById[$item['variation_id']] ?? null : null;
            $variation ??= $product->defaultVariation()->firstOrFail();
            $resolved = SaleLineUnit::resolve($variation, $item);

            $prepared[] = [
                'index' => $index,
                'product_id' => (int) $product->id,
                'variation_id' => (int) $variation->id,
                'unit' => $resolved['unit'] ?? $variation->baseUnit,
                'quantity' => $resolved['quantity'],
                'base_quantity' => $resolved['base_quantity'],
                'charged_unit' => self::decimal($item['price']),
                'approver_uuid' => $item['price_overridden_by'] ?? null,
                'approved_at' => $item['override_approved_at'] ?? null,
            ];
        }

        $quotes = $this->resolver->quote($shop, $customer, array_map(fn (array $line): array => [
            'product_id' => $line['product_id'],
            'variation_id' => $line['variation_id'],
            'unit' => $line['unit'],
            'base_quantity' => $line['base_quantity'],
        ], $prepared), $at);

        $lines = [];
        $refusals = [];
        $subtotal = '0.00';

        foreach ($prepared as $position => $line) {
            $quote = $quotes[$position];
            $charged = UnitPrice::money(bcmul($line['charged_unit'], $line['quantity'], 6));
            $expectedUnit = $quote['unit_price'];
            $expected = $expectedUnit === null ? null : UnitPrice::money(bcmul($expectedUnit, $line['quantity'], 6));
            $subtotal = bcadd($subtotal, $charged, 2);

            $short = $expected !== null && bccomp(bcsub($expected, $charged, 2), '0.01', 2) > 0;

            $approver = null;
            $approved = false;
            if ($short) {
                $approver = $this->approver($line['approver_uuid'], $seller, $online);
                $approved = $approver !== null && $approver->can('sales.override-price');

                if (! $approved && $online) {
                    $refusals[] = ['index' => $line['index'], 'code' => 'price_below_expected', 'expected_unit_price' => $expectedUnit, 'charged_unit_price' => $line['charged_unit']];
                }
            }

            $lines[] = [
                'index' => $line['index'],
                'product_id' => $line['product_id'],
                'quantity' => $line['quantity'],
                'charged_unit_price' => $line['charged_unit'],
                'charged_total' => $charged,
                'expected_unit_price' => $expectedUnit,
                'expected_total' => $expected,
                'short' => $short,
                'approver' => $approver,
                'approved' => $approved,
                'approved_at' => $short && $approved ? ($line['approved_at'] ? Carbon::parse($line['approved_at']) : now()) : null,
            ];
        }

        $discount = $this->discount($sale, $subtotal, $seller, $online);
        if ($discount['refused']) {
            $refusals[] = ['index' => null, 'code' => 'discount_above_limit', 'limit' => $discount['limit']];
        }

        return new PriceAssessment($lines, $discount, $refusals, $online);
    }

    /**
     * The sale's discount against the seller's limit (the highest of their roles' in the business; NULL is no limit).
     *
     * @param  array<string, mixed>  $sale
     * @return array{amount: string, percent: string, limit: ?string, within: bool, approver: ?User, approved: bool, refused: bool}
     */
    private function discount(array $sale, string $subtotal, ?User $seller, bool $online): array
    {
        $amount = self::decimal($sale['discount_amount'] ?? 0);
        $percent = bccomp($subtotal, '0', 2) > 0 ? bcdiv(bcmul($amount, '100', 6), $subtotal, 4) : '0.0000';
        $limit = $this->limitOf($seller);
        $within = $limit === null || bccomp($percent, $limit, 4) <= 0;

        if (bccomp($amount, '0', 2) <= 0 || $within) {
            return ['amount' => $amount, 'percent' => $percent, 'limit' => $limit, 'within' => $within, 'approver' => null, 'approved' => false, 'approved_at' => null, 'refused' => false];
        }

        $approver = $this->approver($sale['discount_approved_by'] ?? null, $seller, $online);
        $approved = $approver !== null && $approver->can('sales.override-price');
        $approvedAt = $approved ? (isset($sale['override_approved_at']) ? Carbon::parse($sale['override_approved_at']) : now()) : null;

        return ['amount' => $amount, 'percent' => $percent, 'limit' => $limit, 'within' => false, 'approver' => $approver, 'approved' => $approved, 'approved_at' => $approvedAt, 'refused' => $online && ! $approved];
    }

    /**
     * The seller's discount limit: NULL when one of their roles has no limit, else the highest; 0 when they have no role.
     */
    private function limitOf(?User $seller): ?string
    {
        if ($seller === null) {
            return '0.00';
        }

        $limits = $seller->roles->pluck('max_discount_percent');

        if ($limits->isEmpty()) {
            return '0.00';
        }
        if ($limits->contains(null)) {
            return null;
        }

        return (string) $limits->map(fn ($value): string => (string) $value)->sort(SORT_NUMERIC)->last();
    }

    /**
     * The person who approved: the one named (a staff uuid), else, online only, the seller. Null when nobody is.
     */
    private function approver(?string $uuid, ?User $seller, bool $online): ?User
    {
        if ($uuid !== null) {
            return User::query()->inCurrentBusiness()->where('uuid', $uuid)->first();
        }

        return $online ? $seller : null;
    }

    private static function decimal(mixed $value): string
    {
        return is_string($value) ? $value : sprintf('%.6F', (float) $value);
    }
}
