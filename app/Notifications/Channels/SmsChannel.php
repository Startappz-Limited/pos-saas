<?php

namespace App\Notifications\Channels;

use App\Http\Startappz\Services\SMSservice;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    public function __construct(protected SMSservice $smsService) {}

    /**
     * Send the given notification via SMS.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification)
            ?? $notifiable->phone
            ?? null;

        if (! $phone) {
            return;
        }

        /** @var \App\Notifications\NewEcommerceOrderNotification $notification */
        $message = $notification->toSms($notifiable);

        if (empty($message)) {
            return;
        }

        try {
            Log::info('SMS notification sending', [
                'phone' => $phone,
                'message_length' => strlen($message),
            ]);

            $result = $this->smsService->sendSms($phone, $message);

            if (! empty($result['success']) && $result['success'] === false) {
                Log::warning('SMS notification failed', [
                    'phone' => $phone,
                    'response' => $result,
                ]);
            } else {
                Log::info('SMS notification sent', [
                    'phone' => $phone,
                    'response' => $result,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SMS notification exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Check if the SMS channel is configured.
     */
    public static function isConfigured(): bool
    {
        return ! empty(env('SMS_API_URL')) && ! empty(env('SMS_API_TOKEN'));
    }
}
