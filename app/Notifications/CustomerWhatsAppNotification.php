<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CustomerWhatsAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Customer $customer,
        public string $message,
    ) {
        $this->onQueue('whatsapp-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if (WhatsAppChannel::isConfigured()) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(object $notifiable): string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'message' => $this->message,
        ];
    }
}
