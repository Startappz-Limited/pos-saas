<?php

namespace App\Notifications;

use App\Models\Business;
use App\Support\BusinessClosureCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the business owner the one-time code that confirms closing their
 * business. Sent straight away (not queued): the owner is waiting for it.
 */
class BusinessClosureCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public Business $business, public string $code) {}

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
            ->subject(__('Your code to close :business', ['business' => $this->business->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Enter this code to confirm that you want to close :business and delete all of its data:', ['business' => $this->business->name]))
            ->line('**'.$this->code.'**')
            ->line(__('The code works for :minutes minutes.', ['minutes' => BusinessClosureCode::MINUTES]))
            ->line(__('If you did not ask to close your business, do not share this code: sign in and change your password straight away.'));
    }
}
