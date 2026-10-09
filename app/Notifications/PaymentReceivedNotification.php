<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  float  $amountPaid  The payment amount just received.
     */
    public function __construct(
        public Sale $sale,
        public float $amountPaid,
    ) {
        $this->onQueue('whatsapp-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (WhatsAppChannel::isConfigured() && $notifiable->phone) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): string
    {
        $sale = $this->sale;
        $sale->loadMissing('shop');

        $shopName = $sale->shop?->name ?? 'Our Store';
        $lines = [];
        $lines[] = '💰 *Payment Received*';
        $lines[] = "_{$shopName}_";
        $lines[] = '';
        $lines[] = "Invoice: {$sale->invoice_number}";
        $lines[] = 'Amount Paid: '.number_format($this->amountPaid, 2);
        $lines[] = 'Total Paid: '.number_format($sale->paid_amount, 2).' / '.number_format($sale->total_amount, 2);

        if ($sale->balance_due > 0) {
            $lines[] = 'Remaining Balance: '.number_format($sale->balance_due, 2);
        } else {
            $lines[] = '✅ *Fully Paid*';
        }

        $lines[] = '';
        $lines[] = 'Thank you for your payment! 🙏';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'amount_paid' => $this->amountPaid,
            'balance_due' => $this->sale->balance_due,
        ];
    }
}
