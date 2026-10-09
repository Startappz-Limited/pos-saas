<?php

namespace App\Notifications;

use App\Models\AbandonedCart;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells shop staff a website cart was abandoned so someone can follow up.
 *
 * Deliberately carries no recovery link: the link restores the customer's cart
 * and is only shown on the cart's own page to staff allowed to see it.
 */
class NewAbandonedCartNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AbandonedCart $cart)
    {
        $this->onQueue('order-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (SmsChannel::isConfigured()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cart = $this->cart;
        $cart->loadMissing(['items', 'shop']);

        $mail = (new MailMessage)
            ->subject("Abandoned cart on {$cart->shop->name}: {$cart->currency} ".number_format((float) $cart->total, 2))
            ->greeting('A website cart was abandoned')
            ->line('**Customer:** '.($cart->customer_name ?: 'Unknown'))
            ->line('**Total:** '.$cart->currency.' '.number_format((float) $cart->total, 2))
            ->line("**Items ({$cart->items->count()}):**");

        foreach ($cart->items as $item) {
            $mail->line("- {$item->name} x{$item->quantity}");
        }

        return $mail
            ->action('View Cart', url("/abandoned-carts/{$cart->uuid}"))
            ->line('Follow up with the customer to recover the sale.');
    }

    public function toSms(object $notifiable): string
    {
        $cart = $this->cart;

        return 'Abandoned cart: '.($cart->customer_name ?: 'a customer').' left '
            .$cart->currency.' '.number_format((float) $cart->total, 2)
            .' on '.$cart->shop->name.'. Follow up in the POS.';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'abandoned_cart_id' => $this->cart->id,
            'customer_name' => $this->cart->customer_name,
            'total' => $this->cart->total,
        ];
    }
}
