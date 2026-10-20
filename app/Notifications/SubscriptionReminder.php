<?php

namespace App\Notifications;

use App\Support\Tenancy\BusinessLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a business's owners about its subscription (Phase 4): the trial or
 * paid period is about to end (`renewal`), or it has lapsed and the business
 * will be closed unless it pays (`closure`).
 */
class SubscriptionReminder extends Notification
{
    use Queueable;

    public const RENEWAL = 'renewal';

    public const CLOSURE = 'closure';

    public function __construct(
        public string $kind,
        private string $businessName,
        private Carbon $date,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = BusinessLink::to('/billing');

        if ($this->kind === self::CLOSURE) {
            return (new MailMessage)
                ->subject(__(':business will be closed unless you renew', ['business' => $this->businessName]))
                ->line(__(':business is read-only because its subscription lapsed. If it is not renewed by :date, the business is closed and its data is erased after a final export.', [
                    'business' => $this->businessName, 'date' => $this->date->toFormattedDayDateString(),
                ]))
                ->action(__('Renew now'), $url);
        }

        return (new MailMessage)
            ->subject(__(':business: your subscription ends soon', ['business' => $this->businessName]))
            ->line(__('The subscription of :business ends on :date. After that the business becomes read-only: you can still view and export your data, but not make changes.', [
                'business' => $this->businessName, 'date' => $this->date->toFormattedDayDateString(),
            ]))
            ->action(__('Pay now'), $url);
    }
}
