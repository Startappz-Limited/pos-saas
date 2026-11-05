<?php

namespace App\Support\Sales;

use App\Actions\Sale\VoidSale;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\EcommerceOrder;
use App\Models\RefundDue;
use App\Models\Sale;
use App\Observers\ProductObserver;
use App\Support\Approvals\Flags;
use Illuminate\Support\Facades\DB;

/**
 * An online order cancelled in its store (stage 10, step 5b; decided 4
 * November 2026), called by both stores' order upsert (webhook and pull) with
 * the status the order had before. A system action: no `sales.void`, no void
 * window. Once only: a sale already voided, or already flagged for the same
 * reason, changes nothing.
 *
 * - Cancelled before dispatch, nothing paid in the store, or paid and fully
 *   refunded there: the sale is voided (stock released, credit reversed; POS
 *   payments are owed as in step 4). A store refund is recorded as a settled
 *   "refunded in store" entry, so nobody owes it again.
 * - Paid in the store but no refund reported: voided, no refund owed, flagged,
 *   and the owners notified; the owner can create a refund owed from the sale.
 * - Part-refunded or part-cancelled in the store (cancelled or not): not
 *   voided; flagged and the owners notified.
 * - Cancelled after dispatch, or the sale has a return that isn't rejected:
 *   not voided; flagged and the shop's staff alerted.
 */
final class StoreCancellations
{
    public const NO_REFUND = 'store_cancelled_unrefunded';

    public const PARTIAL = 'store_partial_cancellation';

    public const AFTER_DISPATCH = 'store_cancelled_after_dispatch';

    public function __construct(private VoidSale $voidSale, private Flags $flags) {}

    /**
     * Called by the stores' order upsert: a converted order's cancellation is
     * handled, and a failure here never fails the sync (it is reported).
     */
    public static function afterSync(EcommerceOrder $order, mixed $previousStatus): void
    {
        if ($order->sale_id === null) {
            return;
        }

        try {
            app(self::class)->handle($order, $previousStatus instanceof \BackedEnum ? $previousStatus->value : $previousStatus);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handle(EcommerceOrder $order, ?string $previousStatus): void
    {
        $sale = $order->sale_id ? Sale::query()->find($order->sale_id) : null;
        if ($sale === null || $sale->status === 'voided') {
            return;
        }

        $facts = $this->facts($order);
        $cancelled = $this->status($order) === 'cancelled';

        if ($facts['partial']) {
            $this->flag($sale, self::PARTIAL, __('The store refunded or cancelled part of order :number (:refunded of :paid). The sale was not voided: check it and process a return for what came back.', [
                'number' => $order->order_number, 'refunded' => number_format($facts['refunded'], 2), 'paid' => number_format($facts['paid'], 2),
            ]), notifyOwners: true);

            return;
        }
        if (! $cancelled) {
            return;
        }

        if ($this->dispatched($order, $previousStatus) || VoidWindow::hasOpenReturns($sale)) {
            $this->flag($sale, self::AFTER_DISPATCH, __('The online order was cancelled after dispatch. Check whether the goods came back, and process a return.'), notifyOwners: false);

            return;
        }

        DB::transaction(function () use ($sale, $order, $facts): void {
            $reason = __('Order cancelled in :store (order :number).', ['store' => ucfirst($order->platform), 'number' => $order->order_number]);

            // The store cancelled it: don't push the restored stock back to it.
            $syncing = ProductObserver::$syncInProgress;
            ProductObserver::$syncInProgress = true;
            try {
                $this->voidSale->execute($sale, $reason, null);
            } finally {
                ProductObserver::$syncInProgress = $syncing;
            }

            AuditableEvent::dispatch(
                auditableType: Sale::class, auditableId: $sale->id, auditableUuid: (string) $sale->uuid, event: AuditEvent::UPDATED,
                newValues: ['status' => 'voided', 'void_reason' => $reason, 'automatic' => true, 'store_paid' => $facts['paid'], 'store_refunded' => $facts['refunded']],
                shopId: (int) $sale->shop_id, tags: ['sale', 'void', 'automatic', $order->platform], businessId: (int) $sale->business_id,
            );

            if ($facts['paid'] > 0 && $facts['refunded'] >= $facts['paid']) {
                RefundDue::create([
                    'shop_id' => $sale->shop_id, 'sale_id' => $sale->id, 'source' => 'store_cancellation', 'method' => 'store',
                    'amount' => $facts['paid'], 'reference' => $facts['reference'], 'status' => 'settled', 'resolution' => 'refunded_in_store',
                    'resolved_reference' => $facts['reference'], 'resolved_at' => now(),
                    'notes' => __('Refunded in :store when the order was cancelled.', ['store' => ucfirst($order->platform)]),
                ]);
            } elseif ($facts['paid'] > 0) {
                $this->flag($sale, self::NO_REFUND, __('Order cancelled but no refund recorded in the store. Check whether the customer was refunded.'), notifyOwners: true);
            }
        });
    }

    /**
     * What the store took and refunded, from the order as the store sent it.
     *
     * @return array{paid: float, refunded: float, reference: ?string, partial: bool}
     */
    public function facts(EcommerceOrder $order): array
    {
        $data = (array) $order->platform_data;
        $total = round((float) ($data['total'] ?? $data['total_price'] ?? $order->total), 2);

        if ($order->platform === 'shopify') {
            $financial = (string) ($data['financial_status'] ?? '');
            $paid = in_array($financial, ['paid', 'partially_refunded', 'refunded'], true) ? $total : 0.0;
            $refunds = collect($data['refunds'] ?? []);
            $refunded = round((float) $refunds->flatMap(fn ($refund) => $refund['transactions'] ?? [])
                ->filter(fn ($transaction) => ($transaction['kind'] ?? '') === 'refund' && ($transaction['status'] ?? 'success') === 'success')
                ->sum(fn ($transaction) => (float) ($transaction['amount'] ?? 0)), 2);
            if ($refunded === 0.0 && $financial === 'refunded') {
                $refunded = $paid;
            }
            $reference = $refunds->isNotEmpty() ? (string) ($refunds->last()['id'] ?? '') : null;
            $lineCancelled = $refunds->flatMap(fn ($refund) => $refund['refund_line_items'] ?? [])->isNotEmpty() && $refunded < $paid;
            $partial = ($refunded > 0 && $refunded < $paid) || $financial === 'partially_refunded' || $lineCancelled;
        } else {
            $paid = ! empty($data['date_paid']) ? $total : 0.0;
            $refunds = collect($data['refunds'] ?? []);
            $refunded = round((float) $refunds->sum(fn ($refund) => abs((float) ($refund['total'] ?? 0))), 2);
            $reference = $refunds->isNotEmpty() ? (string) ($refunds->last()['id'] ?? '') : null;
            $partial = $refunded > 0 && $refunded < $paid;
        }

        return ['paid' => $paid, 'refunded' => $refunded, 'reference' => $reference ?: null, 'partial' => $partial];
    }

    /**
     * "Before dispatch" (step 5b): WooCommerce's previous status wasn't
     * `completed`; Shopify's `fulfillment_status` isn't `fulfilled` or `partial`.
     */
    private function dispatched(EcommerceOrder $order, ?string $previousStatus): bool
    {
        if ($order->platform === 'shopify') {
            return in_array(($order->platform_data['fulfillment_status'] ?? null), ['fulfilled', 'partial'], true);
        }

        return $previousStatus === 'completed';
    }

    private function status(EcommerceOrder $order): string
    {
        return $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;
    }

    /**
     * Flags the sale for review once per reason: an alert on the sale for the
     * shop's staff and, where decided, a notification to the owners.
     */
    private function flag(Sale $sale, string $code, string $message, bool $notifyOwners): void
    {
        $this->flags->raise($sale, (int) $sale->shop_id, (int) $sale->business_id, $code,
            __('Check sale :invoice', ['invoice' => $sale->invoice_number]), $message, route('sales.show', $sale), $notifyOwners);
    }
}
