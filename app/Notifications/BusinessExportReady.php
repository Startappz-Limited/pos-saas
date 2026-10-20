<?php

namespace App\Notifications;

use App\Models\BusinessExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Tells an owner their business's data export is ready (stage 3), with a
 * signed download link; signing in as an owner of the business is still
 * required to download.
 */
class BusinessExportReady extends Notification
{
    use Queueable;

    public const LINK_DAYS = 7;

    public function __construct(public BusinessExport $export, private string $businessName) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('business.exports.download', now()->addDays(self::LINK_DAYS), $this->export);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $closing = $this->export->purpose === BusinessExport::PURPOSE_CLOSURE;

        return (new MailMessage)
            ->subject($closing
                ? __('The final export of :business', ['business' => $this->businessName])
                : __('Your export of :business is ready', ['business' => $this->businessName]))
            ->line($closing
                ? __(':business is closing. This is its final data export: download it before the business is erased.', ['business' => $this->businessName])
                : __('The data export of :business is ready to download.', ['business' => $this->businessName]))
            ->action(__('Download the export'), $this->downloadUrl())
            ->line(__('The link works for :days days, for an owner of the business who is signed in.', ['days' => self::LINK_DAYS]));
    }
}
