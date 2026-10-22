<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the old address when a login's email changes (Phase 6), so a
 * takeover is noticed. It names the new address, never any link.
 */
class EmailAddressChanged extends Notification
{
    use Queueable;

    public function __construct(private string $name, private string $newEmail) {}

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
            ->subject(__('The email address on your account was changed'))
            ->greeting(__('Hello :name,', ['name' => $this->name]))
            ->line(__('The email address on your account was changed to :email.', ['email' => $this->newEmail]))
            ->line(__('If this was you, there is nothing more to do. If it was not, contact support straight away.'));
    }
}
