<?php

namespace App\Actions\Sale;

use App\Actions\CalculateSaleTotals;
use App\Actions\RecordCreditSale;
use App\Actions\SendSaleNotifications;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Services\InvoiceNumberService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records a sale from a validated till payload (Phase 7, step 4): the flat
 * API's sale, taken out of Api\SaleController::store unchanged, so the
 * offline sync push records sales by exactly the same rules.
 *
 * In one transaction: the shop's cash session (opened if none), totals, VAT
 * and profit through CalculateSaleTotals, the register's next invoice number,
 * the lines with their stock taken through the StockLedger (FIFO cost), and
 * a credit sale posted to the customer's account. After it commits: the
 * session's totals and, for a completed sale, the customer's notifications.
 *
 * The caller has already checked the user may act in the shop.
 */
class RecordSale
{
    /**
     * @param  array<string, mixed>  $validated  as StoreSaleRequest validates it
     */
    public function execute(array $validated): RecordedSale
    {
        [$sale, $register, $overLimit] = DB::transaction(function () use ($validated): array {
            // Pre-fetch products and variations
            $productIds = collect($validated['items'])->pluck('product_id')->unique();
            $variationIds = collect($validated['items'])->pluck('variation_id')->filter()->unique();

            $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $variationsById = ProductVariation::whereIn('id', $variationIds)->get()->keyBy('id');

            // The sale's primary shop is the owning shop of its first product.
            // Each line records its own product's shop, so a single sale may
            // span multiple shops while still being attributed correctly.
            $firstProductId = $validated['items'][0]['product_id'];
            $shopId = $productsById[$firstProductId]?->shop_id ?? Shop::first()?->id;

            // Get or auto-open register
            $activeRegister = CashRegister::getActiveRegister($shopId);
            if (! $activeRegister) {
                $activeRegister = CashRegister::openRegister(
                    shopId: $shopId,
                    openingBalance: 0,
                    notes: 'Auto-opened for API sale'
                );
            }

            // Totals, VAT and profit are derived server-side by the same shared
            // action the web POS uses, so the two paths cannot disagree.
            $shop = Shop::find($shopId);

            $totals = app(CalculateSaleTotals::class)->handle(
                items: $validated['items'],
                productsById: $productsById,
                variationsById: $variationsById,
                shop: $shop,
                options: $validated,
            );

            $totalAmount = $totals->totalAmount;

            $isCod = $validated['is_cod'] ?? false;
            $paymentMethod = $validated['payment_method'];

            // Determine status and payment
            if ($isCod) {
                $status = 'pending';
                $paymentStatus = 'unpaid';
                $paidAmount = 0;
                $completedAt = null;
            } elseif ($paymentMethod === 'credit') {
                $status = 'completed';
                $paymentStatus = 'unpaid';
                $paidAmount = 0;
                $completedAt = now();
            } else {
                $status = 'completed';
                $paymentStatus = 'paid';
                $paidAmount = $totalAmount;
                $completedAt = now();
            }

            $balanceDue = $totalAmount - $paidAmount;

            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'shop_id' => $shopId,
                'register_id' => $activeRegister->id,
                'customer_id' => $validated['customer_id'] ?? null,
                'source_id' => $validated['source_id'],
                'delivery_location' => $validated['delivery_location'],
                'walk_in_customer_name' => $validated['walk_in_customer_name'] ?? null,
                'walk_in_customer_email' => $validated['walk_in_customer_email'] ?? null,
                'walk_in_customer_phone' => $validated['walk_in_customer_phone'] ?? null,
                // Snapshotted so a reissued invoice keeps the PIN that was on the original.
                'customer_tax_pin' => $validated['customer_tax_pin']
                    ?? ($validated['customer_id'] ?? null ? Customer::find($validated['customer_id'])?->tax_pin : null),
                'delivery_company_id' => $validated['delivery_company_id'] ?? null,
                'invoice_number' => app(InvoiceNumberService::class)->next($shop ?? $shopId, $activeRegister->register_id),
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
                'expense_notes' => $validated['expense_notes'] ?? null,
                'total_amount' => $totals->totalAmount,
                'total_cost' => $totals->totalCost,
                'total_profit' => $totals->totalProfit,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'is_cod' => $isCod,
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'completed_at' => $completedAt,
                'created_by' => auth()->id(),
            ]);

            // Create sale items
            foreach ($validated['items'] as $index => $item) {
                $product = $productsById[$item['product_id']];
                $variation = isset($item['variation_id']) ? $variationsById[$item['variation_id']] ?? null : null;
                $line = $totals->line($index);

                $saleItem = SaleItem::create([
                    'uuid' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'shop_id' => $product->shop_id ?? $shopId,
                    'variation_id' => $item['variation_id'] ?? null,
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

                // Take the stock from the line's shop, costed oldest layers first.
                app(StockLedger::class)->sellItem($saleItem, $variation ?? $product->defaultVariation()->firstOrFail());
            }
            // FIFO cost and profit onto the sale held in memory (a reload would
            // turn the response's money fields into strings on MySQL).
            $sale->forceFill(app(StockLedger::class)->recostSale($sale->id))->syncOriginal();

            // Post the debt to the customer's credit account, exactly as the web
            // POS does. Inside the sale's transaction so the two can never disagree.
            $recordCreditSale = app(RecordCreditSale::class);
            $overLimit = $paymentMethod === 'credit' && $recordCreditSale->exceedsLimit($sale);
            $recordCreditSale->handle($sale);

            return [$sale, $activeRegister, $overLimit];
        });

        $register->updateSalesTotals();

        // Notify the customer, exactly as the web POS does. COD sales stay
        // pending until delivery, so they are notified on completion instead.
        if ($sale->status === 'completed') {
            app(SendSaleNotifications::class)->execute($sale);
        }

        return new RecordedSale($sale, $overLimit);
    }
}
