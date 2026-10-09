<?php

namespace App\Actions;

use App\Exceptions\AbandonedCartActionException;
use App\Jobs\AbandonedCartWriteBackJob;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSource;
use App\Models\User;
use App\Services\Integration\AbandonedCartWriteBack;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an abandoned website cart into a POS sale — the customer was followed
 * up and bought after all.
 *
 * Mirrors ConvertEcommerceOrderToSale (register handling, stock, conversion
 * stamp) but computes the money with the shared CalculateSaleTotals, so VAT and
 * profit follow the shop's tax settings exactly like a till sale. The website is
 * then told the cart was recovered, with the invoice number as the reference,
 * so it stops sending reminders.
 */
class ConvertAbandonedCartToSale
{
    public function __construct(
        private readonly CalculateSaleTotals $calculateTotals,
        private readonly InvoiceNumberService $invoiceNumbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated ConvertAbandonedCartRequest data.
     *
     * @throws AbandonedCartActionException when the cart cannot be sold as it stands.
     */
    public function execute(AbandonedCart $cart, array $data, User $user): Sale
    {
        $this->guard($cart->loadMissing(['items.product']));

        [$sale, $register] = DB::transaction(function () use ($cart, $data, $user): array {
            $cart = AbandonedCart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $cart->load(['shop', 'items.product']);

            // Re-check under the lock: two people converting at once must not
            // produce two sales.
            $this->guard($cart);

            $shop = $cart->shop;
            $register = $this->register($cart, $data);
            $customer = $this->customer($cart, $data);

            $items = $cart->items->values()->map(fn (AbandonedCartItem $item): array => [
                'product_id' => $item->product_id,
                'variation_id' => $item->variation_id,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
            ])->all();

            $productsById = Product::whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');
            $variationsById = ProductVariation::whereIn('id', array_filter(array_column($items, 'variation_id')))->get()->keyBy('id');

            // The cart's line prices already are what the customer saw, so the
            // legacy (non-VAT) path adds no tax on top of them.
            $totals = $this->calculateTotals->handle(
                items: $items,
                productsById: $productsById,
                variationsById: $variationsById,
                shop: $shop,
                options: [
                    'discount_amount' => $data['discount_amount'] ?? 0,
                    'tax_amount' => 0,
                    'delivery_fee' => $data['delivery_fee'] ?? 0,
                ],
            );

            $isCod = (bool) ($data['is_cod'] ?? false);

            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'shop_id' => $shop->id,
                'register_id' => $register->id,
                'customer_id' => $customer?->id,
                'source_id' => $data['source_id'] ?? $this->abandonedCartSource()->id,
                'delivery_location' => $data['delivery_location'] ?? null,
                'walk_in_customer_name' => $cart->customer_name,
                'walk_in_customer_phone' => $cart->customer_phone,
                'walk_in_customer_email' => $cart->customer_email,
                'customer_tax_pin' => $customer?->tax_pin,
                'delivery_company_id' => $data['delivery_company_id'] ?? null,
                'invoice_number' => $this->invoiceNumbers->next($shop),
                'sale_type' => 'regular',
                'subtotal' => $totals->subtotal,
                'discount_amount' => $totals->discountAmount,
                'tax_amount' => $totals->taxAmount,
                'tax_inclusive' => $totals->taxInclusive,
                'taxable_amount' => $totals->taxableAmount,
                'tax_breakdown' => $totals->breakdown ?: null,
                'delivery_fee' => $totals->deliveryFee,
                'packaging_fee' => $totals->packagingFee,
                'other_expenses' => $totals->otherExpenses,
                'total_amount' => $totals->totalAmount,
                'total_cost' => $totals->totalCost,
                'total_profit' => $totals->totalProfit,
                'paid_amount' => $isCod ? 0 : $totals->totalAmount,
                'balance_due' => $isCod ? $totals->totalAmount : 0,
                'payment_status' => $isCod ? 'unpaid' : 'paid',
                'payment_method' => $data['payment_method'],
                'is_cod' => $isCod,
                'status' => $isCod ? 'pending' : 'completed',
                'notes' => trim("Recovered abandoned website cart #{$cart->platform_cart_id}. ".($data['notes'] ?? '')),
                'completed_at' => $isCod ? null : now(),
                'created_by' => $user->id,
            ]);

            foreach ($items as $index => $item) {
                $product = $productsById[$item['product_id']];
                $variation = $item['variation_id'] ? ($variationsById[$item['variation_id']] ?? null) : null;
                $line = $totals->line($index);

                SaleItem::create([
                    'uuid' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'shop_id' => $product->shop_id ?? $shop->id,
                    'variation_id' => $variation?->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['price'],
                    'line_total' => $line['line_total'],
                    'tax_class' => $line['tax_class'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'taxable_amount' => $line['taxable_amount'],
                    'unit_cost' => $line['unit_cost'],
                    'total_cost' => $line['total_cost'],
                    'profit' => $line['profit'],
                    'profit_margin' => $line['profit_margin'],
                    'status' => 'completed',
                ]);

                if ($variation) {
                    $variation->decrement('stock_quantity', $item['quantity']);
                } else {
                    $product->decrement('stock_quantity', $item['quantity']);
                }
            }

            $cart->markConverted($sale, $user);
            $cart->logActivity(
                "Converted to sale {$sale->invoice_number}",
                'Total '.$cart->currency.' '.number_format((float) $sale->total_amount, 2),
                ['event' => 'converted', 'sale_id' => $sale->id, 'invoice_number' => $sale->invoice_number],
                userId: $user->id,
            );

            // Queued after commit, and never able to fail the sale.
            AbandonedCartWriteBackJob::dispatchSafely(
                $cart,
                AbandonedCartWriteBack::STATUS_RECOVERED,
                $sale->invoice_number,
                "Sold in the shop by {$user->name}",
            );

            return [$sale, $register];
        });

        $register->updateSalesTotals();

        return $sale;
    }

    /**
     * @throws AbandonedCartActionException
     */
    private function guard(AbandonedCart $cart): void
    {
        if ($cart->is_converted) {
            throw new AbandonedCartActionException('This cart has already been converted to a sale.');
        }

        if (! $cart->can_be_converted) {
            throw new AbandonedCartActionException(
                'This cart was already recovered on the website'
                .($cart->platform_order_id ? " as order #{$cart->platform_order_id}" : '')
                .'. Convert the website order instead.'
            );
        }

        if ($cart->items->isEmpty()) {
            throw new AbandonedCartActionException('This cart has no items to sell.');
        }

        $unmatched = $cart->items->reject(fn (AbandonedCartItem $item): bool => $item->isSellable());

        if ($unmatched->isNotEmpty()) {
            throw new AbandonedCartActionException(
                'These items are not linked to a POS product (or variation): '
                .$unmatched->pluck('name')->implode(', ')
                .'. Link the website product to a POS product or give them matching SKUs, then try again.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function register(AbandonedCart $cart, array $data): CashRegister
    {
        if (! empty($data['register_id'])) {
            $register = CashRegister::whereKey($data['register_id'])
                ->where('shop_id', $cart->shop_id)
                ->where('status', 'open')
                ->first();

            if (! $register) {
                throw new AbandonedCartActionException('The selected register is not an open register of this shop.');
            }

            return $register;
        }

        return CashRegister::getActiveRegister($cart->shop_id)
            ?? CashRegister::openRegister(
                shopId: $cart->shop_id,
                openingBalance: 0,
                notes: 'Auto-opened for abandoned cart conversion'
            );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customer(AbandonedCart $cart, array $data): ?Customer
    {
        $customerId = $data['customer_id'] ?? $cart->customer_id;

        if (! $customerId) {
            return null;
        }

        $customer = Customer::whereKey($customerId)->where('shop_id', $cart->shop_id)->first();

        if (! $customer && ! empty($data['customer_id'])) {
            throw new AbandonedCartActionException('The selected customer does not belong to this shop.');
        }

        return $customer;
    }

    private function abandonedCartSource(): SaleSource
    {
        return SaleSource::firstOrCreate(
            ['name' => 'Abandoned Cart'],
            [
                'description' => 'Recovered abandoned cart',
                'icon' => 'solar:cart-large-minimalistic-bold-duotone',
                'color' => '#f59e0b',
                'sort_order' => 6,
                'is_active' => true,
            ],
        );
    }
}
