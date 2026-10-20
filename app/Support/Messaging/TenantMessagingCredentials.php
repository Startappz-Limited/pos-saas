<?php

namespace App\Support\Messaging;

use App\Models\BusinessIntegration;

/**
 * The SMS and WhatsApp Cloud credentials a message is sent with (stage 3).
 *
 * A message sent for a business goes out through that business's own account,
 * never the platform's: with a business set and none saved, nothing is
 * configured. Only code acting for no business at all (the platform's own
 * mail-like messages) reads the platform's `.env` settings.
 */
class TenantMessagingCredentials
{
    /**
     * @return array{url: string, token: string, sender_id: string}|null
     */
    public static function sms(): ?array
    {
        if (current_business() !== null) {
            $credentials = self::saved(BusinessIntegration::TYPE_SMS);

            return filled($credentials['api_url'] ?? null) && filled($credentials['api_token'] ?? null)
                ? ['url' => $credentials['api_url'], 'token' => $credentials['api_token'], 'sender_id' => (string) ($credentials['sender_id'] ?? '')]
                : null;
        }

        return filled(config('services.sms.url')) && filled(config('services.sms.token'))
            ? ['url' => config('services.sms.url'), 'token' => config('services.sms.token'), 'sender_id' => (string) config('services.sms.sender_id')]
            : null;
    }

    /**
     * @return array{url: string, token: string, phone_number_id: string}|null
     */
    public static function whatsappCloud(): ?array
    {
        if (current_business() !== null) {
            $credentials = self::saved(BusinessIntegration::TYPE_WHATSAPP_CLOUD);

            return filled($credentials['api_url'] ?? null) && filled($credentials['api_token'] ?? null)
                ? ['url' => $credentials['api_url'], 'token' => $credentials['api_token'], 'phone_number_id' => (string) ($credentials['phone_number_id'] ?? '')]
                : null;
        }

        return filled(config('services.whatsapp.api_url')) && filled(config('services.whatsapp.api_token'))
            ? ['url' => config('services.whatsapp.api_url'), 'token' => config('services.whatsapp.api_token'), 'phone_number_id' => (string) config('services.whatsapp.phone_number_id')]
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function saved(string $type): array
    {
        return BusinessIntegration::forShop($type)?->credentials ?? [];
    }
}
