<?php

namespace App\Http\Startappz\Services;

use App\Support\Messaging\TenantMessagingCredentials;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSservice
{
    /**
     * Send an SMS
     *
     * @param  string  $senderId
     */
    public function sendSms(string $phone, string $message, ?string $scheduleTime = null): array
    {
        $invalid_phone_numbers = [722000000, '', null, ' ', '0', '254722000000'];

        if (in_array($phone, $invalid_phone_numbers)) {
            return [
                'success' => false,
                'message' => 'Invalid phone number',
            ];
        }

        // Validate phone number format and is correct length
        /*  $phone = preg_replace('/^(?:\+254|254|0)/', '', $phone);
        if (strlen($phone) !== 8) {
            return [
                'success' => false,
                'message' => 'Invalid phone number format',
            ];
        } */

        // The business's own SMS account (stage 3); never the platform's.
        $credentials = TenantMessagingCredentials::sms();

        if ($credentials === null) {
            return [
                'success' => false,
                'message' => 'SMS is not set up for this business',
            ];
        }

        try {
            $payload = [
                'phone' => $phone,
                'senderid' => $credentials['sender_id'],
                'message' => $message,
            ];

            if ($scheduleTime) {
                $payload['schedule_time'] = $scheduleTime;
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$credentials['token'],
            ])->post($credentials['url'], $payload);

            if ($response->successful()) {
                return $response->json();
            }

            // Log error response and return a formatted error message
            Log::error('SMS Sending Failed', [
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send SMS',
                'errors' => $response->json('errors', []),
            ];
        } catch (Exception $e) {
            Log::error('SMS Sending Exception', [
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while sending SMS',
            ];
        }
    }
}
