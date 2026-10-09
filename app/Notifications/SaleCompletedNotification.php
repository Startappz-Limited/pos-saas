<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SaleCompletedNotification extends Notification implements ShouldQueue
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
        $sale->loadMissing(['items.product', 'shop']);

        $shopName = $sale->shop?->name ?? 'Our Store';
        $lines = [];
        $lines[] = "🧾 *Receipt - {$sale->invoice_number}*";
        $lines[] = "_{$shopName}_";
        $lines[] = '';

        foreach ($sale->items as $item) {
            $productName = $item->product?->name ?? 'Item';
            $lines[] = "• {$productName} x{$item->quantity} — ".number_format($item->line_total, 2);
        }

        $lines[] = '';

        if ($sale->discount_amount > 0) {
            $lines[] = 'Discount: -'.number_format($sale->discount_amount, 2);
        }

        if ($sale->delivery_fee > 0) {
            $lines[] = 'Delivery: '.number_format($sale->delivery_fee, 2);
        }

        $lines[] = '*Total: '.number_format($sale->total_amount, 2).'*';

        $paymentLabel = match ($sale->payment_method) {
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money',
            'credit' => 'Credit',
            default => ucfirst($sale->payment_method ?? 'N/A'),
        };

        $lines[] = "Payment: {$paymentLabel}";

        if ($sale->balance_due > 0) {
            $lines[] = 'Balance Due: '.number_format($sale->balance_due, 2);
        }

        $lines[] = '';
        $lines[] = 'Thank you for your purchase';

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
