<?php

namespace App\Notifications\Approvals;

use App\Support\Approvals\ApprovalInbox;
use App\Support\Approvals\ApprovalSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A request waits for someone eligible to approve it (stage 10): in the app
 * and by email, queued. `$reminder` is the one reminder after 24 hours. Not
 * sent once the request is settled (the first approval or rejection wins).
 */
class ApprovalRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ApprovalSubject $subject, public readonly bool $reminder = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return app(ApprovalInbox::class)->isPending($this->subject->kind, $this->subject->uuid);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->reminder ? __('Still waiting: :title', ['title' => $this->subject->title]) : __('Approval needed: :title', ['title' => $this->subject->title])))
            ->line($this->subject->summary)
            ->line(__('Requested by :name.', ['name' => $this->subject->requestedBy]))
            ->action(__('Review'), $this->subject->url)
            ->line(__('The first person to approve or reject it settles it.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...$this->subject->toArray(), 'reminder' => $this->reminder, 'resolved' => null];
    }
}
