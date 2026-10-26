<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\RecordSale;
use App\Actions\SendSaleNotifications;
use App\Actions\SettleCreditSalePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Jobs\SyncOrderStatusJob;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Shop;
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
        // Refuses a user with no shop, or one they can't act in, before anything is made.
        $this->resolveShopId($request);

        try {
            // Resolved here, not injected: the live-like dataset seeder calls store() directly.
            $recorded = app(RecordSale::class)->execute($validated);
        } catch (\Exception $e) {
            return $this->failed($e, __('Failed to create sale:'));
        }

        $sale = $recorded->sale->load(['customer', 'items.product', 'items.variation', 'source', 'createdBy']);

        return response()->json([
            'success' => true,
            'message' => $recorded->overCreditLimit
                ? 'Sale created successfully. Warning: this customer is now over their credit limit.'
                : 'Sale created successfully.',
            'over_credit_limit' => $recorded->overCreditLimit,
            'data' => $sale,
        ], 201);
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
            'customer_id' => ['nullable', 'exists:customers,id'],
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

            return $this->failed($e, __('Failed to collect payment:'));
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
