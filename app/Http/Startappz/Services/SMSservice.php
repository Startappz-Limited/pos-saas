<?php

namespace App\Http\Startappz\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SMSservice
{
    private string $apiUrl;

    private string $apiToken;

    private string $senderId;

    public function __construct()
    {

        $this->apiUrl = env('SMS_API_URL');
        $this->apiToken = env('SMS_API_TOKEN');
        $this->senderId = env('SMS_SENDER_ID');
    }

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

        try {
            $payload = [
                'phone' => $phone,
                'senderid' => $this->senderId,
                'message' => $message,
            ];

            if ($scheduleTime) {
                $payload['schedule_time'] = $scheduleTime;
            }

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$this->apiToken,
            ])->post($this->apiUrl, $payload);

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

    public function send($phone, $msg)
    {
        // Cache settings for 1 year (525600 minutes)
        $sms_settings = Cache::remember('sms_settings', 525600, function () {
            $setting = Setting::where('key', 'sms')->first();

            return $setting ? json_decode($setting->value) : null;
        });

        $query_params = Cache::remember('sms_query_parameters', 525600, function () {
            $setting = Setting::where('key', 'sms_query_parameters')->first();

            return $setting ? json_decode($setting->value) : null;
        });

        if (! $sms_settings || ! $query_params) {
            Log::info('SMS settings or query parameters missing');

            return false;
        }

        $url = $sms_settings->sms_endpoint ?? '';
        $headers = [
            'Accept' => $sms_settings->content_type_accept ?? 'application/json',
            'Content-Type' => $sms_settings->content_type ?? 'application/json',
        ];

        if (! empty($sms_settings->authorization) && $sms_settings->authorization === 'Bearer Token') {
            $headers['Authorization'] = 'Bearer '.$sms_settings->sms_api_key;
        } elseif (! empty($sms_settings->authorization) && $sms_settings->authorization === 'Basic Auth') {
            $query_obj = $query_params;
            $username = $query_obj->parameters->username->value ?? '';
            $password = $query_obj->parameters->password->value ?? '';
            $headers['Authorization'] = 'Basic '.base64_encode($username.':'.$password);
        }

        // Build data array dynamically from query parameters
        $data = [];
        foreach ($query_params->parameters as $key => $param) {
            if ($param->value === 'phone') {
                $data[$key] = $phone;
            } elseif ($param->value === 'senderid') {
                $data[$key] = $sms_settings->sms_sender_id ?? '';
            } elseif ($param->value === 'message') {
                $data[$key] = $msg;
            } else {
                $data[$key] = $param->value;
            }
        }

        try {
            $request_method = strtoupper($sms_settings->request_method ?? 'POST');
            if ($request_method === 'POST') {
                $response = Http::withHeaders($headers)->post($url, $data);
            } else {
                $response = Http::withHeaders($headers)->get($url, $data);
            }
            Log::info('SMS Response: '.$response->body());

            return $response->json();
        } catch (\Exception $e) {
            Log::error('SMS Send Error: '.$e->getMessage());

            return false;
        }
    }
}
