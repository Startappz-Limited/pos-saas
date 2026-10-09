<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Confirms to the owner that their business will be deleted at the end of
 * the grace period, and how to stop it.
 */
class BusinessClosureScheduled extends Notification
{
    use Queueable;

    public function __construct(public Business $business) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->business->purge_after?->toFormattedDayDateString();

        return (new MailMessage)
            ->subject(__(':business will be closed on :date', ['business' => $this->business->name, 'date' => $date]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You asked to close :business. On :date its shops, products, customers, sales, staff accounts and all other data will be permanently deleted, including your own account.', [
                'business' => $this->business->name,
                'date' => $date,
            ]))
            ->line(__('Until then, only you can sign in. Your staff can no longer use the system.'))
            ->line(__('A final export of the data is being prepared; we will email you a link to it.'))
            ->action(__('Change your mind'), route('business.closing'))
            ->line(__('If you did not ask for this, sign in and cancel the closure straight away, then change your password.'));
    }
}
