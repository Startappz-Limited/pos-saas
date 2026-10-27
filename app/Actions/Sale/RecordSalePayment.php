<?php

namespace App\Actions\Sale;

use App\Actions\SendSaleNotifications;
use App\Actions\SettleCreditSalePayment;
use App\Jobs\SyncOrderStatusJob;
use App\Models\Sale;
use App\Models\SalePayment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records payments against a sale (Phase 7, step 6): the flat API's "collect
 * payment", taken out of Api\SaleController::collectPayment unchanged, so the
 * till's sync push records payments by the same rules.
 *
 * In one transaction: each payment, its settlement of a credit sale's debt,
 * and the sale's paid amount, balance and status. After it commits: the
 * customer's notice, an online order completed when paid in full, and the
 * cash session's totals. Returns the sale, refreshed.
 *
 * The caller has already checked the user may act in the sale's shop and that
 * the sale isn't fully paid.
 */
class RecordSalePayment
{
    /**
     * @param  list<array{amount: mixed, payment_method: string, reference?: ?string, notes?: ?string}>  $payments
     * @param  CarbonInterface|null  $paidAt  a till's offline payment keeps its time; none is now
     * @param  string|null  $clientUuid  the till's id for a single pushed payment
     */
    public function execute(Sale $sale, array $payments, ?CarbonInterface $paidAt = null, ?string $clientUuid = null, ?int $receivedBy = null): Sale
    {
        [$totalPayment, $paymentStatus] = DB::transaction(function () use ($sale, $payments, $paidAt, $clientUuid, $receivedBy): array {
            $totalPayment = 0;
            $settleCreditPayment = app(SettleCreditSalePayment::class);

            foreach ($payments as $paymentData) {
                $payment = SalePayment::create([
                    'uuid' => (string) Str::uuid(),
                    'client_uuid' => $clientUuid,
                    'sale_id' => $sale->id,
                    'payment_number' => 'PAY-'.strtoupper(Str::random(8)),
                    'amount' => $paymentData['amount'],
                    'payment_method' => $paymentData['payment_method'],
                    'reference' => $paymentData['reference'] ?? null,
                    'notes' => $paymentData['notes'] ?? null,
                    'paid_at' => $paidAt ?? now(),
                    'received_by' => $receivedBy ?? auth()->id(),
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
                'completed_at' => $paymentStatus === 'paid' && ! $sale->completed_at ? ($paidAt ?? now()) : $sale->completed_at,
            ]);

            return [$totalPayment, $paymentStatus];
        });

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

        return $sale;
    }
}
