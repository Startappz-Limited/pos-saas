<?php

namespace App\Notifications\Approvals;

use App\Support\Approvals\ApprovalSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever made a request how it was settled (stage 10): approved or
 * rejected, by whom, and the reason given. Queued; in the app and by email.
 */
class ApprovalSettled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ApprovalSubject $subject,
        public readonly string $outcome,
        public readonly string $settledBy,
        public readonly ?string $note = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->outcome === 'approved'
                ? __('Approved: :title', ['title' => $this->subject->title])
                : __('Rejected: :title', ['title' => $this->subject->title]))
            ->line($this->outcome === 'approved'
                ? __(':name approved it.', ['name' => $this->settledBy])
                : __(':name rejected it.', ['name' => $this->settledBy]));

        if ($this->note !== null && $this->note !== '') {
            $mail->line(__('Reason: :note', ['note' => $this->note]));
        }

        return $mail->action(__('Open'), $this->subject->url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...$this->subject->toArray(), 'outcome' => $this->outcome, 'settled_by' => $this->settledBy, 'note' => $this->note];
    }
}
