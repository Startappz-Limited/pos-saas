<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;

    protected string $apiToken;

    protected string $phoneNumberId;

    public function __construct()
    {
        $this->apiUrl = (string) config('services.whatsapp.api_url', '');
        $this->apiToken = (string) config('services.whatsapp.api_token', '');
        $this->phoneNumberId = (string) config('services.whatsapp.phone_number_id', '');
    }

    /**
     * Send a text message via WhatsApp.
     *
     * @return array{success: bool, message: string, response?: array<string, mixed>}
     */
    public function sendTextMessage(string $phone, string $message): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'WhatsApp service is not configured',
            ];
        }

        if (! $this->isValidPhone($phone)) {
            return [
                'success' => false,
                'message' => 'Invalid phone number',
            ];
        }

        return $this->sendRequest($phone, [
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $message,
            ],
        ]);
    }

    /**
     * Send a template message via WhatsApp.
     *
     * @param  array<string, mixed>  $components
     * @return array{success: bool, message: string, response?: array<string, mixed>}
     */
    public function sendTemplateMessage(string $phone, string $templateName, string $languageCode = 'en', array $components = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'WhatsApp service is not configured',
            ];
        }

        if (! $this->isValidPhone($phone)) {
            return [
                'success' => false,
                'message' => 'Invalid phone number',
            ];
        }

        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        if (! empty($components)) {
            $template['components'] = $components;
        }

        return $this->sendRequest($phone, [
            'type' => 'template',
            'template' => $template,
        ]);
    }

    /**
     * Send the HTTP request to the WhatsApp API.
     *
     * @param  array<string, mixed>  $content
     * @return array{success: bool, message: string, response?: array<string, mixed>}
     */
    protected function sendRequest(string $phone, array $content): array
    {
        try {
            $payload = array_merge([
                'messaging_product' => 'whatsapp',
                'to' => $phone,
            ], $content);

            Log::info('WhatsApp message sending', [
                'phone' => $phone,
                'type' => $content['type'] ?? 'unknown',
            ]);

            $response = Http::asJson()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->apiToken,
                ])
                ->post($this->apiUrl, $payload);

            if ($response->successful()) {
                Log::info('WhatsApp message sent', [
                    'phone' => $phone,
                    'status' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'message' => 'Message sent successfully',
                    'response' => $response->json(),
                ];
            }

            Log::warning('WhatsApp message failed', [
                'phone' => $phone,
                'status' => $response->status(),
                'response_body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send WhatsApp message',
                'response' => $response->json(),
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp message exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while sending WhatsApp message',
            ];
        }
    }

    /**
     * Validate the phone number.
     */
    protected function isValidPhone(string $phone): bool
    {
        $invalidPhoneNumbers = ['', '0', '722000000', '254722000000'];

        if (in_array($phone, $invalidPhoneNumbers, true)) {
            return false;
        }

        // Must contain only digits, optionally prefixed by +
        if (! preg_match('/^\+?\d{7,15}$/', $phone)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the WhatsApp service is configured.
     */
    public static function isConfigured(): bool
    {
        return ! empty(config('services.whatsapp.api_url'))
            && ! empty(config('services.whatsapp.api_token'));
    }
}
