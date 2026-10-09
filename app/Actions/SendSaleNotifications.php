<?php

namespace App\Actions;

use App\Jobs\SendSaleInvoiceViaBaileysJob;
use App\Models\Customer;
use App\Models\Sale;
use App\Notifications\CreditBalanceNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\SaleCompletedNotification;
use Illuminate\Support\Facades\Log;

/**
 * Notify a customer that their sale is complete, across every configured channel.
 *
 * Shared by the web POS and the API used by the Flutter client so a sale rung up
 * on a phone messages the customer exactly like one rung up at the desk. Must be
 * called AFTER the enclosing transaction commits — the Baileys job re-reads the
 * sale from the database.
 *
 * Delivery is best-effort by design: a messaging outage must never fail a sale.
 */
class SendSaleNotifications
{
    public function execute(Sale $sale): void
    {
        $customer = $this->notifiableCustomer($sale);

        if (! $customer) {
            return;
        }

        // Official WhatsApp Cloud API path (queued on `whatsapp-notifications`).
        if ($sale->shop?->notifiesReceiptOnSale() ?? true) {
            try {
                if ($sale->payment_method === 'credit') {
                    $customer->notify(new CreditBalanceNotification($sale));
                } else {
                    $customer->notify(new SaleCompletedNotification($sale));
                }
            } catch (\Throwable $e) {
                Log::warning('Sale customer notification dispatch failed', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Baileys path (queued on `baileys`) — delivers the invoice PDF itself.
        // NotifyCustomerOfSale re-checks the switch, since the job is also
        // dispatchable directly.
        $this->dispatchInvoice($sale);
    }

    /**
     * Notify a customer that a payment landed against their sale.
     *
     * When the payment clears the balance the customer also receives a fresh
     * invoice PDF, now stamped PAID — this is the settlement path for credit and
     * partially-paid sales.
     */
    public function paymentReceived(Sale $sale, float $amount): void
    {
        $customer = $this->notifiableCustomer($sale);

        if (! $customer) {
            return;
        }

        if ($sale->shop?->notifiesReceiptOnSale() ?? true) {
            try {
                $customer->notify(new PaymentReceivedNotification($sale, $amount));
            } catch (\Throwable $e) {
                Log::warning('Sale payment notification dispatch failed', [
                    'sale_id' => $sale->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($sale->payment_status === 'paid') {
            $this->dispatchInvoice($sale);
        }
    }

    /**
     * The customer to message, or null when this sale reaches nobody.
     *
     * Honours the shop's master switch — off means no channel fires at all.
     */
    private function notifiableCustomer(Sale $sale): ?Customer
    {
        if (! $sale->customer_id) {
            return null;
        }

        if (! ($sale->shop?->notifiesOnSale() ?? true)) {
            return null;
        }

        // fresh(): CreditBalanceNotification reports the customer's running
        // credit balance, which the credit ledger updates during the sale's own
        // transaction. A relation loaded earlier in the request still holds the
        // pre-sale balance, so the customer would be told they owe nothing.
        $customer = $sale->relationLoaded('customer')
            ? $sale->customer?->fresh()
            : $sale->customer;

        return $customer?->phone ? $customer : null;
    }

    /**
     * Queue the invoice PDF for WhatsApp delivery. Never throws.
     */
    private function dispatchInvoice(Sale $sale): void
    {
        if (! ($sale->shop?->notifiesInvoiceOnSale() ?? true)) {
            return;
        }

        try {
            SendSaleInvoiceViaBaileysJob::dispatch($sale);
        } catch (\Throwable $e) {
            Log::warning('Baileys sale invoice dispatch failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
