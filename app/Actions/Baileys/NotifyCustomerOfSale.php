<?php

namespace App\Actions\Baileys;

use App\Actions\GenerateSaleInvoicePdf;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Customer;
use App\Models\Sale;
use App\Notifications\SaleCompletedNotification;
use App\Support\BaileysJid;
use Illuminate\Support\Facades\Log;

/**
 * Phase 2: notify a customer over Baileys when their POS sale completes.
 *
 * This is independent of the official WhatsApp template flow
 * ({@see SaleCompletedNotification}). It only fires
 * when a connected Baileys session exists for the sale's shop and the
 * `baileys.notify_on_sale` config flag is enabled.
 *
 * The customer receives the invoice PDF as a WhatsApp document with the receipt
 * summary as its caption. Baileys has no 24-hour session window and needs no
 * pre-approved template, so unlike the official Cloud API path this delivers to
 * a walk-in customer who has never messaged the shop. If the PDF cannot be
 * rendered the message degrades to plain text rather than failing outright.
 */
class NotifyCustomerOfSale
{
    public function __construct(
        protected SendBaileysMessage $sendMessage,
        protected SendBaileysMediaMessage $sendMedia,
        protected GenerateSaleInvoicePdf $invoicePdf,
    ) {}

    public function execute(Sale $sale): ?BaileysMessage
    {
        // Per-shop switch, defaulting to the `baileys.notify_on_sale` global.
        // Toggled from the Baileys sessions screen.
        if (! ($sale->shop?->notifiesInvoiceOnSale() ?? config('baileys.notify_on_sale', true))) {
            return null;
        }

        if (! $sale->customer_id) {
            return null;
        }

        /** @var Customer|null $customer */
        $customer = $sale->customer;

        if (! $customer || empty($customer->phone)) {
            return null;
        }

        $jid = BaileysJid::fromPhone($customer->phone);

        if (! $jid) {
            return null;
        }

        $session = BaileysSession::query()
            ->where('shop_id', $sale->shop_id)
            ->connected()
            ->latest('connected_at')
            ->first();

        if (! $session) {
            return null;
        }

        $caption = $this->buildMessage($sale);

        return $this->sendInvoice($sale, $session, $jid, $customer, $caption)
            ?? $this->sendMessage->execute(
                session: $session,
                toJid: $jid,
                text: $caption,
                sentBy: $sale->created_by ?? null,
                customerId: $customer->id,
            );
    }

    /**
     * Attempt to deliver the invoice PDF as a WhatsApp document.
     *
     * Returns null when the PDF could not be produced, letting the caller fall
     * back to the plain-text receipt.
     */
    protected function sendInvoice(
        Sale $sale,
        BaileysSession $session,
        string $jid,
        Customer $customer,
        string $caption,
    ): ?BaileysMessage {
        try {
            $bytes = $this->invoicePdf->execute($sale);
        } catch (\Throwable $e) {
            Log::warning('Sale invoice PDF render failed, falling back to text', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // The gateway is handed the bytes inline (base64) rather than a URL so it
        // never has to reach back into this app — no DNS, TLS or firewall
        // dependency between the Node process and Laravel. `media_url` still
        // records the signed link for the audit trail and re-download.
        return $this->sendMedia->execute(
            session: $session,
            toJid: $jid,
            mediaType: 'document',
            url: $this->invoicePdf->signedUrl($sale),
            caption: $caption,
            extras: [
                'mimetype' => 'application/pdf',
                'filename' => $this->invoicePdf->filename($sale),
                'data' => base64_encode($bytes),
            ],
            sentBy: $sale->created_by ?? null,
            customerId: $customer->id,
        );
    }

    protected function buildMessage(Sale $sale): string
    {
        $sale->loadMissing(['items.product', 'shop']);

        $shopName = $sale->shop?->name ?? 'Our Store';
        $lines = [];
        $lines[] = "🧾 *Invoice {$sale->invoice_number}*";
        $lines[] = "_{$shopName}_";
        $lines[] = '';

        foreach ($sale->items as $item) {
            $productName = $item->product?->name ?? 'Item';
            $lines[] = "• {$productName} x{$item->quantity} — ".number_format((float) $item->line_total, 2);
        }

        $lines[] = '';
        $lines[] = '*Total: '.number_format((float) $sale->total_amount, 2).'*';

        if ((float) $sale->balance_due > 0) {
            $lines[] = 'Paid: '.number_format((float) $sale->paid_amount, 2);
            $lines[] = 'Balance Due: '.number_format((float) $sale->balance_due, 2);
        } else {
            $lines[] = 'Status: PAID';
        }

        $lines[] = '';
        $lines[] = 'Thank you for your purchase';

        return implode("\n", $lines);
    }
}
