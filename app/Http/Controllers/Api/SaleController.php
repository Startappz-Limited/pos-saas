<?php

namespace App\Http\Controllers\Api;

use App\Actions\CalculateSaleTotals;
use App\Actions\RecordCreditSale;
use App\Actions\SendSaleNotifications;
use App\Actions\SettleCreditSalePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Jobs\SyncOrderStatusJob;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Shop;
use App\Rules\ExistsForViewer;
use App\Services\InvoiceNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $shopId = $request->integer('shop_id') ?: null;

        if ($shopId !== null && ! $user->canAccessShop($shopId)) {
            abort(403);
        }

        $query = Sale::with(['customer', 'source', 'createdBy'])
            ->visibleTo($user)
            ->when($shopId, fn ($query): mixed => $query->where('shop_id', $shopId))
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // The mobile client sends customer_id from the customer detail screen to
        // list that customer's unpaid sales. It was previously ignored, so every
        // customer was shown the whole shop's unpaid sales as if they owed them.
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('walk_in_customer_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $sales = $query->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $shopId = $this->resolveShopId($request);

        DB::beginTransaction();
        try {
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
                'invoice_number' => app(InvoiceNumberService::class)->next($shop ?? $shopId),
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

                SaleItem::create([
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

                // Update stock
                if ($variation) {
                    $variation->decrement('stock_quantity', $item['quantity']);
                } else {
                    $product->decrement('stock_quantity', $item['quantity']);
                }
            }

            // Post the debt to the customer's credit account, exactly as the web
            // POS does. Inside the sale's transaction so the two can never disagree.
            $recordCreditSale = app(RecordCreditSale::class);
            $overLimit = $paymentMethod === 'credit' && $recordCreditSale->exceedsLimit($sale);
            $recordCreditSale->handle($sale);

            DB::commit();

            $activeRegister->updateSalesTotals();

            // Notify the customer, exactly as the web POS does. COD sales stay
            // pending until delivery, so they are notified on completion instead.
            if ($sale->status === 'completed') {
                app(SendSaleNotifications::class)->execute($sale);
            }

            $sale->load(['customer', 'items.product', 'items.variation', 'source', 'createdBy']);

            return response()->json([
                'success' => true,
                'message' => $overLimit
                    ? 'Sale created successfully. Warning: this customer is now over their credit limit.'
                    : 'Sale created successfully.',
                'over_credit_limit' => $overLimit,
                'data' => $sale,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Sale $sale): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($sale->shop_id), 403);

        $sale->load(['customer', 'shop', 'items.product', 'items.variation', 'createdBy', 'source', 'deliveryCompany', 'payments.receiver']);

        return response()->json([
            'success' => true,
            'data' => $sale,
        ]);
    }

    public function update(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($sale->shop_id), 403);

        $validated = $request->validate([
            'customer_id' => ['nullable', new ExistsForViewer(Customer::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $sale->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Sale updated successfully.',
            'data' => $sale,
        ]);
    }

    public function destroy(Sale $sale): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($sale->shop_id), 403);

        $sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sale deleted successfully.',
        ]);
    }

    public function complete(Sale $sale): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($sale->shop_id), 403);

        $sale->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($sale->register_id) {
            $sale->register->updateSalesTotals();
        }

        app(SendSaleNotifications::class)->execute($sale);

        $ecommerceOrder = $sale->ecommerceOrder;
        if ($ecommerceOrder && $ecommerceOrder->status->canChangeStatus()) {
            try {
                SyncOrderStatusJob::dispatchSync($ecommerceOrder, 'completed', 'Sale completed');
                $ecommerceOrder->update(['status' => 'completed']);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => 'Sale completed but failed to sync order to website: '.$e->getMessage(),
                    'data' => $sale,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale completed successfully.',
            'data' => $sale,
        ]);
    }

    public function void(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($sale->shop_id), 403);

        $validated = $request->validate([
            'void_reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($sale, $validated): void {
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $validated['void_reason'],
            ]);

            // Release the debt a voided credit sale created, or the customer
            // keeps owing for a sale that no longer exists.
            app(SettleCreditSalePayment::class)->reverse($sale, $validated['void_reason']);
        });

        if ($sale->register_id) {
            $sale->register->updateSalesTotals();
        }

        return response()->json([
            'success' => true,
            'message' => 'Sale voided successfully.',
            'data' => $sale,
        ]);
    }

    public function collectPayment(Request $request, Sale $sale): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($sale->shop_id), 403);

        if ($sale->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This sale is already fully paid.',
            ], 422);
        }

        $validated = $request->validate([
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method' => ['required', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::beginTransaction();
        try {
            $totalPayment = 0;
            $settleCreditPayment = app(SettleCreditSalePayment::class);

            foreach ($validated['payments'] as $paymentData) {
                $payment = SalePayment::create([
                    'uuid' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'payment_number' => 'PAY-'.strtoupper(Str::random(8)),
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method'],
                    'reference' => $paymentData['reference'] ?? null,
                    'notes' => $paymentData['notes'] ?? null,
                    'paid_at' => now(),
                    'received_by' => auth()->id(),
                ]);

                // Settle the matching debt on the credit account. No-op unless
                // this sale was sold on credit.
                $settleCreditPayment->handle($sale, $payment);

                $totalPayment += $paymentData['amount'];
            }

            $newPaidAmount = $sale->paid_amount + $totalPayment;
            $newBalanceDue = $sale->total_amount - $newPaidAmount;

            if ($newBalanceDue <= 0) {
                $paymentStatus = 'paid';
                $newBalanceDue = 0;
            } elseif ($newPaidAmount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            $sale->update([
                'paid_amount' => $newPaidAmount,
                'balance_due' => $newBalanceDue,
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus === 'paid' ? 'completed' : $sale->status,
                'completed_at' => $paymentStatus === 'paid' && ! $sale->completed_at ? now() : $sale->completed_at,
            ]);

            DB::commit();

            app(SendSaleNotifications::class)->paymentReceived($sale->fresh(), (float) $totalPayment);

            if ($paymentStatus === 'paid') {
                $ecommerceOrder = $sale->ecommerceOrder;
                if ($ecommerceOrder && $ecommerceOrder->status->canChangeStatus()) {
                    try {
                        SyncOrderStatusJob::dispatchSync($ecommerceOrder, 'completed', 'Payment collected in full');
                        $ecommerceOrder->update(['status' => 'completed']);
                    } catch (\Exception $e) {
                        // Log but don't fail the payment
                    }
                }
            }

            $sale->refresh();

            if ($sale->register_id) {
                $sale->register->updateSalesTotals();
            }

            $sale->load('payments.receiver');

            return response()->json([
                'success' => true,
                'message' => 'Payment collected successfully.',
                'data' => $sale,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to collect payment: '.$e->getMessage(),
            ], 500);
        }
    }

    public function payments(Sale $sale): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($sale->shop_id), 403);

        $payments = $sale->payments()->with('receiver')->latest('paid_at')->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
        ]);
    }

    private function resolveShopId(Request $request): int
    {
        $user = $request->user();
        $shopId = $request->integer('shop_id') ?: ($user->shop_id ?? Shop::query()->visibleTo($user)->value('id'));

        abort_if($shopId === null, 422, 'No shop is available for this action.');
        abort_unless($user->canAccessShop((int) $shopId), 403);

        return (int) $shopId;
    }
}
