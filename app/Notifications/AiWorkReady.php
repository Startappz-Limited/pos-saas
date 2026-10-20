<?php

namespace App\Notifications;

use App\Models\AiBlockedWork;
use App\Support\Tenancy\BusinessLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the person whose AI request waited for credit that it has now run
 * (Phase 4, decision 4.9).
 */
class AiWorkReady extends Notification
{
    use Queueable;

    public function __construct(public AiBlockedWork $work) {}

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
            ->subject(__('Your AI request has been completed'))
            ->line(__('The AI request that was waiting for credit has now run. Open the campaign to see the result.'))
            ->action(__('Open campaigns'), BusinessLink::to('/campaigns'));
    }
}
