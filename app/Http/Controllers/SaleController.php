<?php

namespace App\Http\Controllers;

use App\Actions\CalculateSaleTotals;
use App\Actions\GenerateSaleInvoicePdf;
use App\Actions\RecordCreditSale;
use App\Actions\SendSaleNotifications;
use App\Actions\SettleCreditSalePayment;
use App\Http\Requests\StoreSaleRequest;
use App\Jobs\SyncOrderStatusJob;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use App\Services\InvoiceNumberService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of sales.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $shops = $this->availableShops($user);
        $selectedShopId = $this->selectedShopId($request, $shops);

        $query = Sale::with(['customer', 'shop'])
            ->visibleTo($user)
            ->when($selectedShopId, fn ($query): mixed => $query->where('shop_id', $selectedShopId))
            ->latest('created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereIn('customer_id', function ($sub) use ($search) {
                        $sub->select('id')
                            ->from('customers')
                            ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Kept in step with the API index, which the mobile client uses to list
        // one customer's unpaid sales.
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        $sales = $query->paginate(20);

        $statisticsQuery = DB::table('sales')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed")
            ->selectRaw("COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending")
            ->selectRaw("COUNT(CASE WHEN status = 'voided' THEN 1 END) as voided");

        $statistics = (array) $this->applyShopFilter($statisticsQuery, $user, $selectedShopId)->first();

        return view('sales.index', compact('sales', 'statistics', 'shops', 'selectedShopId'));
    }

    /**
     * Show the form for creating a new sale.
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Sale::class);

        $shops = $this->availableShops($request->user());
        $shopId = $this->selectedShopId($request, $shops) ?? $this->defaultShopId($request->user(), $shops);
        $activeRegister = CashRegister::getActiveRegister($shopId);

        // Auto-open register if none exists for today
        if (! $activeRegister) {
            $activeRegister = CashRegister::openRegister(
                shopId: $shopId,
                openingBalance: 0,
                notes: 'Auto-opened on first sale'
            );
        }

        $customers = Customer::select('id', 'name', 'phone', 'customer_type')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $products = Product::with('variations')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $saleSources = SaleSource::active()
            ->ordered()
            ->get();

        $deliveryCompanies = DeliveryCompany::active()
            ->orderBy('name')
            ->get();

        // Drives whether the till offers a manual tax box: once a shop is VAT
        // registered the server computes the tax and ignores anything typed here,
        // so leaving the field editable would just mislead the cashier.
        $vatRegistered = (bool) Shop::whereKey($shopId)->value('vat_registered');

        return view('sales.create', compact('customers', 'products', 'activeRegister', 'saleSources', 'deliveryCompanies', 'shops', 'shopId', 'vatRegistered'));
    }

    /**
     * Show the fullscreen POS interface.
     */
    public function pos(Request $request): View
    {
        $this->authorize('create', Sale::class);

        $shops = $this->availableShops($request->user());
        $shopId = $this->selectedShopId($request, $shops) ?? $this->defaultShopId($request->user(), $shops);
        $activeRegister = CashRegister::getActiveRegister($shopId);

        // Auto-open register if none exists for today
        if (! $activeRegister) {
            $activeRegister = CashRegister::openRegister(
                shopId: $shopId,
                openingBalance: 0,
                notes: 'Auto-opened on first sale'
            );
        }

        $customers = Customer::select('id', 'name', 'phone', 'customer_type')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $products = Product::with('variations')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $categories = Category::where('status', 'active')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $saleSources = SaleSource::active()
            ->ordered()
            ->get();

        $deliveryCompanies = DeliveryCompany::active()
            ->orderBy('name')
            ->get();

        $vatRegistered = (bool) Shop::whereKey($shopId)->value('vat_registered');

        return view('sales.pos', compact('customers', 'products', 'categories', 'activeRegister', 'saleSources', 'deliveryCompanies', 'shops', 'shopId', 'vatRegistered'));
    }

    /**
     * Store a newly created sale in storage.
     */
    public function store(StoreSaleRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', Sale::class);

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $shopId = $this->resolveActionShopId($request);
            $activeRegister = CashRegister::getActiveRegister($shopId);

            // Auto-open register if none exists for today
            if (! $activeRegister) {
                $activeRegister = CashRegister::openRegister(
                    shopId: $shopId,
                    openingBalance: 0,
                    notes: 'Auto-opened on first sale'
                );
            }

            // Pre-fetch all products and variations needed for this sale
            $productIds = collect($validated['items'])->pluck('product_id')->unique()->all();
            $variationIds = collect($validated['items'])->pluck('variation_id')->filter()->unique()->all();

            $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');
            $variationsById = ! empty($variationIds)
                ? ProductVariation::whereIn('id', $variationIds)->get()->keyBy('id')
                : collect();

            // Totals, VAT and profit are derived server-side by a single shared
            // action so the web POS and the API can never disagree about them.
            $shop = Shop::find($shopId);

            $totals = app(CalculateSaleTotals::class)->handle(
                items: $validated['items'],
                productsById: $productsById,
                variationsById: $variationsById,
                shop: $shop,
                options: $validated,
            );

            $totalAmount = $totals->totalAmount;

            // Determine payment status based on payment method and COD
            $isCod = $validated['is_cod'] ?? false;
            $isCredit = $validated['payment_method'] === 'credit';

            if ($isCod) {
                // COD: Sale is pending until payment is collected
                $paidAmount = 0;
                $balanceDue = $totalAmount;
                $paymentStatus = 'unpaid';
                $status = 'pending';
                $completedAt = null;
            } elseif ($isCredit) {
                // Credit: Full amount is due
                $paidAmount = 0;
                $balanceDue = $totalAmount;
                $paymentStatus = 'unpaid';
                $status = 'completed';
                $completedAt = now();
            } else {
                // Immediate payment (cash, card, etc.)
                $paidAmount = $totalAmount;
                $balanceDue = 0;
                $paymentStatus = 'paid';
                $status = 'completed';
                $completedAt = now();
            }

            // Create sale
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
                'payment_method' => $validated['payment_method'],
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

            // Post the debt to the customer's credit account. Inside the sale's
            // own transaction so the sale and the debt it creates can never
            // disagree, and before commit so a ledger failure rolls the sale back.
            $recordCreditSale = app(RecordCreditSale::class);
            $overLimit = $isCredit && $recordCreditSale->exceedsLimit($sale);
            $recordCreditSale->handle($sale);

            DB::commit();

            // Send WhatsApp notifications to customer
            $this->sendSaleNotifications($sale);

            $message = $overLimit
                ? 'Sale created successfully. Warning: this customer is now over their credit limit.'
                : 'Sale created successfully.';

            // Return JSON for AJAX requests (POS page)
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'uuid' => $sale->uuid,
                        'invoice_number' => $sale->invoice_number,
                        'total_amount' => $sale->total_amount,
                        'over_credit_limit' => $overLimit,
                        'receipt_url' => route('sales.receipt', $sale),
                        'show_url' => route('sales.show', $sale),
                    ],
                ]);
            }

            return redirect()
                ->route('sales.show', $sale)
                ->with($overLimit ? 'warning' : 'success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create sale: '.$e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create sale: '.$e->getMessage());
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'shop', 'items.product', 'items.variation', 'createdBy', 'source', 'deliveryCompany', 'payments.receiver']);

        return view('sales.show', compact('sale'));
    }

    /**
     * Display the receipt for printing.
     */
    public function receipt(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load(['customer', 'shop', 'items.product', 'items.variation', 'createdBy', 'source']);

        return view('sales.receipt', compact('sale'));
    }

    /**
     * Download the sale invoice as a PDF (public signed URL).
     *
     * Reached by customers from the link attached to their WhatsApp invoice, so
     * it is deliberately unauthenticated — the signature is the access control.
     */
    public function invoicePdf(Sale $sale, GenerateSaleInvoicePdf $invoicePdf): HttpResponse
    {
        return response($invoicePdf->execute($sale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoicePdf->filename($sale).'"',
        ]);
    }

    /**
     * Show the form for editing the specified sale.
     */
    public function edit(Sale $sale): View
    {
        $this->authorize('update', $sale);

        $sale->load('items.product');
        $customers = Customer::select('id', 'name', 'phone', 'customer_type')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $products = Product::select('id', 'name', 'sku', 'selling_price', 'cost_price', 'stock_quantity', 'status')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('sales.edit', compact('sale', 'customers', 'products'));
    }

    /**
     * Update the specified sale in storage.
     */
    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $sale->update($validated);

        return redirect()
            ->route('sales.show', $sale)
            ->with('success', 'Sale updated successfully.');
    }

    /**
     * Remove the specified sale from storage.
     */
    public function destroy(Sale $sale): RedirectResponse
    {
        $this->authorize('delete', $sale);

        $sale->delete();

        return redirect()
            ->route('sales.index')
            ->with('success', 'Sale deleted successfully.');
    }

    /**
     * Display pending sales.
     */
    public function pending(): View
    {
        $user = auth()->user();

        $sales = Sale::with(['customer', 'shop'])
            ->visibleTo($user)
            ->where('status', 'pending')
            ->latest('created_at')
            ->paginate(20);

        return view('sales.pending', compact('sales'));
    }

    /**
     * Display completed sales.
     */
    public function completed(): View
    {
        $user = auth()->user();

        $sales = Sale::with(['customer', 'shop'])
            ->visibleTo($user)
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(20);

        return view('sales.completed', compact('sales'));
    }

    /**
     * Complete a sale.
     */
    public function complete(Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        $sale->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Send WhatsApp notification to customer
        $this->sendSaleNotifications($sale);

        // Sync ecommerce order to completed on the platform
        $ecommerceOrder = $sale->ecommerceOrder;
        if ($ecommerceOrder && $ecommerceOrder->status->canChangeStatus()) {
            try {
                SyncOrderStatusJob::dispatchSync($ecommerceOrder, 'completed', 'Sale completed');
                $ecommerceOrder->update(['status' => 'completed']);
            } catch (\Exception $e) {
                return redirect()
                    ->back()
                    ->with('warning', 'Sale completed but failed to sync order to website: '.$e->getMessage());
            }
        }

        return redirect()
            ->back()
            ->with('success', 'Sale completed successfully.');
    }

    /**
     * Void a sale.
     */
    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('void', $sale);

        $validated = $request->validate([
            'void_reason' => 'required|string|max:255',
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

        return redirect()
            ->back()
            ->with('success', 'Sale voided successfully.');
    }

    /**
     * Display daily sales report.
     */
    public function dailyReport(): View
    {
        $user = auth()->user();
        $today = now()->startOfDay();

        $salesQuery = Sale::with(['customer', 'shop'])
            ->visibleTo($user)
            ->where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->latest('completed_at');

        $statisticsQuery = DB::table('sales')
            ->where('status', 'completed')
            ->whereDate('completed_at', $today)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as total_profit')
            ->selectRaw('COUNT(*) as total_transactions');

        $statistics = (array) $this->applyShopFilter($statisticsQuery, $user)->first();

        $sales = $salesQuery->get();

        return view('sales.daily-report', compact('sales', 'statistics'));
    }

    /**
     * Display profit report.
     */
    public function profitReport(Request $request): View
    {
        $user = $request->user();
        $startDate = $request->input('start_date', now()->startOfMonth());
        $endDate = $request->input('end_date', now()->endOfMonth());

        $salesQuery = Sale::with(['customer', 'shop'])
            ->visibleTo($user)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->latest('completed_at');

        $statisticsQuery = DB::table('sales')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_sales')
            ->selectRaw('COALESCE(SUM(total_cost), 0) as total_cost')
            ->selectRaw('COALESCE(SUM(total_profit), 0) as total_profit')
            ->selectRaw('CASE WHEN SUM(total_amount) > 0 THEN (SUM(total_profit) / SUM(total_amount)) * 100 ELSE 0 END as profit_margin');

        $statistics = (array) $this->applyShopFilter($statisticsQuery, $user)->first();

        $sales = $salesQuery->get();

        return view('sales.profit-report', compact('sales', 'statistics', 'startDate', 'endDate'));
    }

    /**
     * Store a new sale source via AJAX.
     */
    public function storeSaleSource(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:sale_sources,name',
            'description' => 'nullable|string|max:500',
        ]);

        $saleSource = SaleSource::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'sort_order' => SaleSource::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sale source created successfully.',
            'data' => [
                'id' => $saleSource->id,
                'name' => $saleSource->name,
            ],
        ]);
    }

    /**
     * Store a new delivery company via AJAX.
     */
    public function storeDeliveryCompany(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'contact_person' => 'nullable|string|max:255',
        ]);

        $deliveryCompany = DeliveryCompany::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'contact_person' => $validated['contact_person'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery company created successfully.',
            'data' => [
                'id' => $deliveryCompany->id,
                'name' => $deliveryCompany->name,
                'phone' => $deliveryCompany->phone,
            ],
        ]);
    }

    /**
     * Collect payment for a COD sale.
     */
    public function collectPayment(Request $request, Sale $sale)
    {
        $this->authorize('collectPayment', $sale);

        // Validate that sale can accept payments
        if ($sale->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'This sale is already fully paid.',
            ], 422);
        }

        $validated = $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.payment_method' => 'required|in:cash,card,bank_transfer,mobile_money,cheque',
            'payments.*.reference' => 'nullable|string|max:255',
            'payments.*.notes' => 'nullable|string|max:500',
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

            // Update sale payment tracking
            $newPaidAmount = $sale->paid_amount + $totalPayment;
            $newBalanceDue = $sale->total_amount - $newPaidAmount;

            // Determine new payment status
            if ($newBalanceDue <= 0) {
                $paymentStatus = 'paid';
                $newBalanceDue = 0;
            } elseif ($newPaidAmount > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            // Update sale
            $sale->update([
                'paid_amount' => $newPaidAmount,
                'balance_due' => $newBalanceDue,
                'payment_status' => $paymentStatus,
                'status' => $paymentStatus === 'paid' ? 'completed' : $sale->status,
                'completed_at' => $paymentStatus === 'paid' && ! $sale->completed_at ? now() : $sale->completed_at,
            ]);

            DB::commit();

            // Send WhatsApp payment notification to customer, plus a PAID invoice
            // once the balance is cleared.
            app(SendSaleNotifications::class)->paymentReceived($sale->fresh(), (float) $totalPayment);

            // If fully paid, sync ecommerce order to completed on the platform
            if ($paymentStatus === 'paid') {
                $ecommerceOrder = $sale->ecommerceOrder;
                if ($ecommerceOrder && $ecommerceOrder->status->canChangeStatus()) {
                    try {
                        SyncOrderStatusJob::dispatchSync($ecommerceOrder, 'completed', 'Payment collected in full');
                        $ecommerceOrder->update(['status' => 'completed']);
                    } catch (\Exception $e) {
                        Log::warning('Failed to sync ecommerce order to completed after payment', [
                            'sale_id' => $sale->id,
                            'order_id' => $ecommerceOrder->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment collected successfully.',
                'data' => [
                    'paid_amount' => $newPaidAmount,
                    'balance_due' => $newBalanceDue,
                    'payment_status' => $paymentStatus,
                    'sale_status' => $sale->fresh()->status,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to collect payment: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment history for a sale.
     */
    public function getPayments(Sale $sale)
    {
        $this->authorize('view', $sale);

        $payments = $sale->payments()
            ->with('receiver:id,name')
            ->orderBy('paid_at', 'desc')
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'payment_method_label' => $payment->payment_method_label,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'paid_at' => $payment->paid_at->format('M d, Y h:i A'),
                'received_by' => $payment->receiver?->name ?? 'N/A',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'payments' => $payments,
                'total_paid' => $sale->paid_amount,
                'balance_due' => $sale->balance_due,
                'payment_status' => $sale->payment_status,
            ],
        ]);
    }

    /**
     * @return Collection<int, Shop>
     */
    private function availableShops(User $user): EloquentCollection
    {
        return Shop::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function selectedShopId(Request $request, EloquentCollection $shops): ?int
    {
        $shopId = $request->integer('shop_id') ?: null;

        if ($shopId !== null && ! $shops->contains('id', $shopId)) {
            abort(403);
        }

        return $shopId;
    }

    private function defaultShopId(User $user, EloquentCollection $shops): int
    {
        $shopId = $user->shop_id ?? $shops->first()?->id;

        abort_if($shopId === null, 422, 'No shop is available for this action.');

        return (int) $shopId;
    }

    private function resolveActionShopId(Request $request): int
    {
        $shops = $this->availableShops($request->user());

        return $this->selectedShopId($request, $shops) ?? $this->defaultShopId($request->user(), $shops);
    }

    private function applyShopFilter(QueryBuilder $query, User $user, ?int $shopId = null): QueryBuilder
    {
        if ($shopId !== null) {
            return $query->where('shop_id', $shopId);
        }

        if ($user->hasShopRestrictions()) {
            return $query->whereIn('shop_id', $user->assignedShopIds());
        }

        return $query;
    }

    /**
     * Send WhatsApp notifications for a completed sale.
     *
     * Delegates to the shared action so the API (Flutter client) behaves identically.
     */
    private function sendSaleNotifications(Sale $sale): void
    {
        app(SendSaleNotifications::class)->execute($sale);
    }
}
