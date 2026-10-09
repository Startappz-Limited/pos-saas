<?php

namespace App\Notifications;

use App\Models\BusinessExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the business owner a link to their finished export. The link needs
 * the owner to be signed in: the file holds every customer's details.
 */
class BusinessExportReady extends Notification
{
    use Queueable;

    public function __construct(public BusinessExport $export) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->export->business;
        $expires = $this->export->expires_at?->toFormattedDayDateString();

        $message = (new MailMessage)
            ->subject(__('Your data export for :business is ready', ['business' => $business->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The export of all the data held for :business is ready to download.', ['business' => $business->name]))
            ->action(__('Download export'), route('business.exports.download', $this->export))
            ->line(__('You will be asked to sign in. The link works until :date.', ['date' => $expires]))
            ->line(__('The file contains your customers\' and staff\'s personal details: store it securely.'));

        if ($this->export->is_final) {
            $message->line(__('This is the final export, taken when you asked to close the business. All the business\'s data will be deleted on :date.', [
                'date' => $business->purge_after?->toFormattedDayDateString(),
            ]));
        } else {
            $message->line(__('Once you have downloaded it, you can close the business from your profile page.'));
        }

        return $message;
    }
}
