<?php

namespace App\Notifications\Channels;

use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Customer;
use App\Models\WhatsAppMessage;
use App\Notifications\Messages\WhatsAppTemplateMessage;
use App\Services\WhatsAppService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WhatsAppChannel
{
    public function __construct(protected WhatsAppService $whatsAppService) {}

    /**
     * Send the given notification via WhatsApp.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('whatsapp', $notification)
            ?? $notifiable->phone
            ?? null;

        if (! $phone) {
            Log::debug('WhatsApp notification skipped: no phone number', [
                'notifiable_type' => get_class($notifiable),
            ]);

            return;
        }

        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if (empty($message)) {
            return;
        }

        if ($message instanceof WhatsAppTemplateMessage) {
            $result = $this->whatsAppService->sendTemplateMessage(
                $phone,
                $message->templateName,
                $message->languageCode,
                $message->components,
            );

            $this->logTemplateMessage($notifiable, $phone, $message, $result);
        } else {
            $result = $this->whatsAppService->sendTextMessage($phone, $message);

            $this->logMessage($notifiable, $phone, $message, $result);
        }

        if (! $result['success']) {
            Log::warning('WhatsApp notification failed, falling back to SMS', [
                'phone' => $phone,
                'error' => $result['message'],
            ]);

            $this->fallbackToSms($notifiable, $notification);
        }
    }

    /**
     * Attempt to send an SMS as fallback when WhatsApp delivery fails.
     */
    protected function fallbackToSms(object $notifiable, Notification $notification): void
    {
        if (! SmsChannel::isConfigured()) {
            return;
        }

        if (! method_exists($notification, 'toSms')) {
            return;
        }

        try {
            app(SmsChannel::class)->send($notifiable, $notification);

            Log::info('SMS fallback sent successfully after WhatsApp failure');
        } catch (\Exception $e) {
            Log::error('SMS fallback also failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the WhatsApp message to the database.
     *
     * @param  array{success: bool, message: string, response?: array<string, mixed>}  $result
     */
    protected function logMessage(object $notifiable, string $phone, string $content, array $result): void
    {
        try {
            $shopId = $notifiable->shop_id ?? (property_exists($notifiable, 'shops') ? $notifiable->shops?->first()?->id : null);
            $customerId = $notifiable instanceof Customer ? $notifiable->id : null;
            $sentBy = auth()->id();

            if (! $shopId) {
                return;
            }

            $whatsAppMessage = WhatsAppMessage::create([
                'shop_id' => $shopId,
                'customer_id' => $customerId,
                'sent_by' => $sentBy,
                'phone' => $phone,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => WhatsAppMessageType::Text,
                'content' => $content,
                'status' => WhatsAppMessageStatus::Pending,
            ]);

            if ($result['success']) {
                $whatsappMessageId = $result['response']['messages'][0]['id'] ?? null;
                $whatsAppMessage->markAsSent($whatsappMessageId);
            } else {
                $whatsAppMessage->markAsFailed($result['message']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log WhatsApp message to database', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log the WhatsApp template message to the database.
     *
     * @param  array{success: bool, message: string, response?: array<string, mixed>}  $result
     */
    protected function logTemplateMessage(object $notifiable, string $phone, WhatsAppTemplateMessage $template, array $result): void
    {
        try {
            $shopId = $notifiable->shop_id ?? (property_exists($notifiable, 'shops') ? $notifiable->shops?->first()?->id : null);
            $customerId = $notifiable instanceof Customer ? $notifiable->id : null;
            $sentBy = auth()->id();

            if (! $shopId) {
                return;
            }

            $whatsAppMessage = WhatsAppMessage::create([
                'shop_id' => $shopId,
                'customer_id' => $customerId,
                'sent_by' => $sentBy,
                'phone' => $phone,
                'direction' => WhatsAppMessageDirection::Outbound,
                'message_type' => WhatsAppMessageType::Template,
                'content' => "Template: {$template->templateName}",
                'template_name' => $template->templateName,
                'status' => WhatsAppMessageStatus::Pending,
            ]);

            if ($result['success']) {
                $whatsappMessageId = $result['response']['messages'][0]['id'] ?? null;
                $whatsAppMessage->markAsSent($whatsappMessageId);
            } else {
                $whatsAppMessage->markAsFailed($result['message']);
            }
        } catch (\Exception $e) {
            Log::error('Failed to log WhatsApp template message to database', [
                'phone' => $phone,
                'template' => $template->templateName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if the WhatsApp channel is configured.
     */
    public static function isConfigured(): bool
    {
        return WhatsAppService::isConfigured();
    }
}
