<?php

namespace App\Notifications;

use App\Models\OwnerConfirmation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one-time code an owner enters to confirm closing (or handing over) a
 * business (stage 3). Sent straight away, not queued: the plain code exists
 * only here.
 */
class OwnerConfirmationCode extends Notification
{
    use Queueable;

    public function __construct(private string $code, private string $purpose, private string $businessName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function code(): string
    {
        return $this->code;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $action = $this->purpose === OwnerConfirmation::PURPOSE_CLOSE
            ? __('close :business', ['business' => $this->businessName])
            : __('hand over :business', ['business' => $this->businessName]);

        return (new MailMessage)
            ->subject(__('Your confirmation code to :action', ['action' => $action]))
            ->line(__('Enter this code to :action:', ['action' => $action]))
            ->line("**{$this->code}**")
            ->line(__('It expires in :minutes minutes. If you did not ask for it, someone may be using your account: change your password.', [
                'minutes' => OwnerConfirmation::EXPIRES_AFTER_MINUTES,
            ]));
    }
}
