<?php

namespace App\Notifications;

use App\Models\EcommerceOrder;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppTemplateMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class NewEcommerceOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public EcommerceOrder $order)
    {
        $this->onQueue('order-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (WhatsAppChannel::isConfigured()) {
            $channels[] = WhatsAppChannel::class;
        } elseif (SmsChannel::isConfigured()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order;
        $order->loadMissing('items');
        $url = url("/ecommerce-orders/{$order->uuid}");

        $mail = (new MailMessage)
            ->subject("New Order {$order->order_number} from {$order->shop->name}")
            ->greeting("New {$order->shop->name} Website Order!")
            ->line("A new order has been received from your **{$order->platform}** store.")
            ->line("**Order:** {$order->order_number}")
            ->line("**Customer:** {$order->customer_name}")
            ->line("**Total:** {$order->currency} " . number_format($order->total, 2))
            ->line("**Items ({$order->items->count()}):**");

        foreach ($order->items as $item) {
            $mail->line("- {$item->name} x{$item->quantity}");
        }

        return $mail
            ->action('View Order', $url)
            ->line('Please review and process this order.');
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(object $notifiable): string
    {
        $order = $this->order;

        return "New order {$order->order_number} from {$order->customer_name}. "
            . "Total: {$order->currency} " . number_format($order->total, 2) . '. '
            . ucfirst($order->platform) . " - {$order->shop->name}";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): WhatsAppTemplateMessage
    {
        $order = $this->order;
        $order->loadMissing('items');

        $itemsSummary = $order->items
            ->map(fn($item) => "{$item->name} x{$item->quantity}")
            ->implode(', ');

        return (new WhatsAppTemplateMessage(
            templateName: 'new_order_notification_utility',
            languageCode: 'en',
        ))->bodyParameters([
            $order->shop->name,
            $order->order_number,
            $order->customer_name,
            $order->currency . ' ' . number_format($order->total, 2),
            (string) $order->items->count(),
            $itemsSummary,
        ])->buttonUrl(0, $this->getInvoiceUrlSuffix($order));
    }

    /**
     * Extract the dynamic suffix for the Meta template URL button.
     *
     * Meta template has static prefix: https://pos.startappz.co.ke/orders/{{1}}
     * So we only need to pass the path after "/orders/" plus the query string.
     */
    private function getInvoiceUrlSuffix(EcommerceOrder $order): string
    {
        $signedUrl = URL::signedRoute('orders.invoice-pdf', $order);
        $parsed = parse_url($signedUrl);

        $path = ltrim($parsed['path'] ?? '', '/');
        $suffix = str($path)->after('orders/')->toString();

        if (! empty($parsed['query'])) {
            $suffix .= '?' . $parsed['query'];
        }

        return $suffix;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'platform' => $this->order->platform,
            'total' => $this->order->total,
            'customer_name' => $this->order->customer_name,
        ];
    }
}
