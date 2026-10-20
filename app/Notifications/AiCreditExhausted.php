<?php

namespace App\Notifications;

use App\Support\Tenancy\BusinessLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a business's owners that its prepaid AI credit has run out and AI work
 * is waiting for a top-up (Phase 4, decision 4.9). Sent once per empty spell.
 */
class AiCreditExhausted extends Notification
{
    use Queueable;

    public function __construct(private string $businessName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':business: out of AI credit', ['business' => $this->businessName]))
            ->line(__('The AI credit of :business has run out, so AI requests are waiting. Top up (from KES :min) and they will run automatically.', [
                'business' => $this->businessName,
                'min' => config('billing.ai_min_topup', 500),
            ]))
            ->action(__('Top up'), BusinessLink::to('/billing'));
    }
}
