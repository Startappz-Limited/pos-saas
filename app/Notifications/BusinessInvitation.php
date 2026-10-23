<?php

namespace App\Notifications;

use App\Models\BusinessInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying an invite link (stage 3). Sent straight away rather than
 * queued: the plain token exists only here and in the link.
 */
class BusinessInvitation extends Notification
{
    use Queueable;

    public function __construct(public BusinessInvite $invite, private string $token) {}

    public function acceptUrl(): string
    {
        return route('invitations.show', $this->token);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->invite->business->name;
        $inviter = $this->invite->inviter?->name;

        return (new MailMessage)
            ->subject(__('You are invited to join :business', ['business' => $business]))
            ->line($inviter
                ? __(':inviter invited you to join :business.', ['inviter' => $inviter, 'business' => $business])
                : __('You are invited to join :business.', ['business' => $business]))
            ->action(__('Accept the invitation'), $this->acceptUrl())
            ->line(__('The invitation expires in :hours hours, on :date.', ['hours' => BusinessInvite::EXPIRES_AFTER_HOURS, 'date' => $this->invite->expires_at->toDayDateTimeString()]))
            ->line(__('If you were not expecting it, you can ignore this email.'));
    }
}
