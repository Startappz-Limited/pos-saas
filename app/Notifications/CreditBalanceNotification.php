<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CreditBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Sale $sale)
    {
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
        $lines[] = '📋 *Credit Sale Recorded*';
        $lines[] = "_{$shopName}_";
        $lines[] = '';
        $lines[] = "Invoice: {$sale->invoice_number}";
        $lines[] = 'Amount Due: '.number_format($sale->total_amount, 2);

        if ($notifiable->credit_balance > 0) {
            $lines[] = 'Total Credit Balance: '.number_format($notifiable->credit_balance, 2);
        }

        if ($notifiable->credit_limit > 0) {
            $remaining = $notifiable->credit_limit - $notifiable->credit_balance;
            $lines[] = 'Available Credit: '.number_format(max(0, $remaining), 2);
        }

        $lines[] = '';
        $lines[] = 'Please settle your balance at your earliest convenience.';

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
            'total_amount' => $this->sale->total_amount,
        ];
    }
}
