<?php

namespace App\Notifications;

use App\Support\Tenancy\BusinessLink;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Tells a business's owners that a paired till hasn't synced for a day
 * (Phase 7, decision 4.5), naming the till and its last sync. Its sales are
 * still being taken; they reach the reports only once it syncs.
 */
class TillSyncOverdue extends Notification
{
    use Queueable;

    public function __construct(
        private string $businessName,
        private string $shopName,
        private string $tillName,
        private string $tillCode,
        private ?Carbon $lastSyncedAt,
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
        $since = $this->lastSyncedAt
            ? __('It last synced on :time.', ['time' => $this->lastSyncedAt->toDayDateTimeString()])
            : __('It hasn\'t synced since it was paired.');

        return (new MailMessage)
            ->subject(__(':business: the till ":till" hasn\'t synced', ['business' => $this->businessName, 'till' => $this->tillName]))
            ->line(__('The till ":till" (:code) at :shop hasn\'t been in touch with the server for over :hours hours.', [
                'till' => $this->tillName, 'code' => $this->tillCode, 'shop' => $this->shopName, 'hours' => (int) config('sync.overdue_hours'),
            ]))
            ->line($since)
            ->line(__('It can keep selling, but its sales won\'t show in your reports, and its stock won\'t update, until it connects. Check that it is switched on and online.'))
            ->action(__('See your tills'), BusinessLink::to('/dashboard'));
    }
}
