<?php

namespace App\Actions;

use App\Jobs\SyncOrderStatusJob;
use App\Models\CashRegister;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConvertEcommerceOrderToSale
{
    public function __construct(
        protected WooCommerceOrderSyncService $wooCommerceOrderSyncService,
        protected ShopifyOrderSyncService $shopifyOrderSyncService
    ) {}

    /**
     * Convert an e-commerce order to a local sale.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(EcommerceOrder $order, array $data, User $user): Sale
    {
        if (! $order->can_be_converted) {
            throw new \RuntimeException('This order cannot be converted to a sale.');
        }

        $this->syncOrderItemsToPlatform($order, $data);

        return DB::transaction(function () use ($order, $data, $user) {
            $order->refresh()->load(['shop', 'items.product']);
            $shopId = $order->shop_id;

            // Ensure we have a register
            $registerId = $data['register_id'] ?? null;
            if (! $registerId) {
                $activeRegister = CashRegister::getActiveRegister($shopId);
                if (! $activeRegister) {
                    $activeRegister = CashRegister::openRegister(
                        shopId: $shopId,
                        openingBalance: 0,
                        notes: 'Auto-opened for e-commerce order conversion'
                    );
                }
                $registerId = $activeRegister->id;
            }

            // Calculate totals from order items
            $subtotal = 0;
            $totalCost = 0;
            $items = collect($data['items'] ?? [])->values();
            $productsById = Product::whereIn('id', $items->pluck('product_id')->filter()->unique()->all())
                ->get()
                ->keyBy('id');
            $variationsById = ProductVariation::whereIn('id', $items->pluck('variation_id')->filter()->unique()->all())
                ->get()
                ->keyBy('id');

            foreach ($items as $itemData) {
                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $lineTotal = $quantity * $unitPrice;
                $subtotal += $lineTotal;

                // Calculate cost from matched product
                $unitCost = 0;
                $productId = $itemData['product_id'];
                $variationId = $itemData['variation_id'] ?? null;

                if ($variationId) {
                    $variation = $variationsById->get((int) $variationId);
                    if ($variation) {
                        $unitCost = $variation->cost_price ?? 0;
                    }
                } elseif ($productId) {
                    $product = $productsById->get((int) $productId);
                    if ($product) {
                        $unitCost = $product->cost_price ?? 0;
                    }
                }

                $totalCost += $quantity * $unitCost;
            }

            $discountAmount = (float) ($order->discount_total ?? 0);
            $taxAmount = (float) ($order->tax_total ?? 0);
            $deliveryFee = (float) ($data['delivery_fee'] ?? $order->shipping_total ?? 0);
            $totalAmount = $subtotal - $discountAmount + $taxAmount + $deliveryFee;
            // VAT is collected on the revenue authority's behalf and is never
            // margin, so it comes out before profit. The platform's own
            // `tax_total` is authoritative here: it is what the customer was
            // actually charged at checkout, so it is not recomputed.
            $totalProfit = $totalAmount - $taxAmount - $totalCost;

            // Determine payment status from order
            $isCod = $order->is_cod;
            $paymentMethod = $isCod ? 'cash' : ($data['payment_method'] ?? $order->payment_method ?? 'online');

            if ($isCod) {
                // COD = not yet paid, sale stays pending
                $paidAmount = 0;
                $balanceDue = $totalAmount;
                $paymentStatus = 'unpaid';
                $saleStatus = 'pending';
                $completedAt = null;
            } else {
                // Already paid online
                $paidAmount = $totalAmount;
                $balanceDue = 0;
                $paymentStatus = 'paid';
                $saleStatus = 'completed';
                $completedAt = now();
            }

            // Create the sale
            $sale = Sale::create([
                'uuid' => (string) Str::uuid(),
                'shop_id' => $shopId,
                'register_id' => $registerId,
                'customer_id' => $data['customer_id'] ?? null,
                'source_id' => $data['source_id'],
                'delivery_location' => $data['delivery_location'] ?? null,
                'walk_in_customer_name' => $order->customer_name,
                'walk_in_customer_phone' => $order->customer_phone,
                'walk_in_customer_email' => $order->customer_email,
                'delivery_company_id' => $data['delivery_company_id'] ?? null,
                'invoice_number' => app(InvoiceNumberService::class)->next($shopId),
                'sale_type' => 'regular',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'tax_inclusive' => false,
                'taxable_amount' => round($subtotal - $discountAmount, 2),
                'delivery_fee' => $deliveryFee,
                'packaging_fee' => 0,
                'other_expenses' => 0,
                'expense_notes' => null,
                'total_amount' => $totalAmount,
                'total_cost' => $totalCost,
                'total_profit' => $totalProfit,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'is_cod' => $isCod,
                'status' => $saleStatus,
                'notes' => "Converted from e-commerce order #{$order->order_number} ({$order->platform})",
                'completed_at' => $completedAt,
                'created_by' => $user->id,
            ]);

            // Create sale items and decrement stock
            foreach ($items as $itemData) {
                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $lineTotal = $quantity * $unitPrice;

                $productId = $itemData['product_id'];
                $variationId = $itemData['variation_id'] ?? null;

                $unitCost = 0;
                $variation = null;
                $product = null;

                if ($variationId) {
                    $variation = $variationsById->get((int) $variationId);
                    if ($variation) {
                        $unitCost = $variation->cost_price ?? 0;
                    }
                } elseif ($productId) {
                    $product = $productsById->get((int) $productId);
                    if ($product) {
                        $unitCost = $product->cost_price ?? 0;
                    }
                }

                $totalItemCost = $quantity * $unitCost;
                $profit = $lineTotal - $totalItemCost;
                $profitMargin = $lineTotal > 0 ? ($profit / $lineTotal) * 100 : 0;

                SaleItem::create([
                    'uuid' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'product_id' => $productId,
                    'variation_id' => $variationId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => 0,
                    'line_total' => $lineTotal,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalItemCost,
                    'profit' => $profit,
                    'profit_margin' => $profitMargin,
                    'status' => 'completed',
                ]);

                // Decrement stock for matched products
                if ($variation) {
                    $variation->decrement('stock_quantity', $quantity);
                } elseif ($product) {
                    $product->decrement('stock_quantity', $quantity);
                }
            }

            // Mark the order as converted
            $order->markConverted($sale, $user);

            // Sync status back to the platform
            $platformStatus = $isCod ? 'processing' : 'completed';
            SyncOrderStatusJob::dispatch($order, $platformStatus, 'Converted to local sale '.$sale->invoice_number);

            return $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncOrderItemsToPlatform(EcommerceOrder $order, array $data): void
    {
        $order->loadMissing(['shop', 'items']);

        $items = $data['items'] ?? [];
        $deliveryFee = isset($data['delivery_fee']) ? (float) $data['delivery_fee'] : null;

        $result = match ($order->platform) {
            'woocommerce' => $this->wooCommerceOrderSyncService->syncOrderItems($order->shop, $order, $items, $deliveryFee),
            'shopify' => $this->shopifyOrderSyncService->syncOrderItems($order->shop, $order, $items, $deliveryFee),
            default => ['success' => false, 'message' => "Unsupported platform: {$order->platform}"],
        };

        if (! ($result['success'] ?? false)) {
            throw new \RuntimeException('Could not update website order items: '.($result['message'] ?? 'Unknown platform sync error.'));
        }
    }
}
