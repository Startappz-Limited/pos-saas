<?php

namespace App\Services\Integration;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceService
{
    /**
     * Test connection to WooCommerce store
     *
     * @param  array  $config  Configuration with store_url, consumer_key, consumer_secret
     * @return array{connected: bool, message: string, details: array}
     */
    public function testConnection(array $config): array
    {
        try {
            $storeUrl = rtrim($config['store_url'] ?? '', '/');
            $consumerKey = $config['consumer_key'] ?? '';
            $consumerSecret = $config['consumer_secret'] ?? '';

            if (empty($storeUrl) || empty($consumerKey) || empty($consumerSecret)) {
                return [
                    'connected' => false,
                    'message' => 'Missing required credentials',
                    'details' => [],
                ];
            }

            // Test connection by fetching system status
            $response = Http::timeout(10)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get("{$storeUrl}/wp-json/wc/v3/system_status");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'connected' => true,
                    'message' => 'Successfully connected to WooCommerce',
                    'details' => [
                        'store_name' => $data['settings']['title'] ?? 'Unknown',
                        'version' => $data['environment']['version'] ?? 'Unknown',
                        'currency' => $data['settings']['currency'] ?? 'Unknown',
                    ],
                ];
            }

            return [
                'connected' => false,
                'message' => 'Failed to connect: ' . $response->status(),
                'details' => ['status_code' => $response->status()],
            ];
        } catch (\Exception $e) {
            Log::error('WooCommerce connection test failed', [
                'error' => $e->getMessage(),
                'store_url' => $config['store_url'] ?? 'N/A',
            ]);

            return [
                'connected' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
                'details' => [],
            ];
        }
    }

    /**
     * Decrypt and test connection using encrypted credentials
     *
     * @param  array  $encryptedConfig  Configuration with encrypted credentials
     * @return array{connected: bool, message: string, details: array}
     */
    public function testConnectionWithEncrypted(array $encryptedConfig): array
    {
        try {
            $config = [
                'store_url' => $encryptedConfig['store_url'] ?? '',
                'consumer_key' => isset($encryptedConfig['consumer_key'])
                    ? Crypt::decrypt($encryptedConfig['consumer_key'])
                    : '',
                'consumer_secret' => isset($encryptedConfig['consumer_secret'])
                    ? Crypt::decrypt($encryptedConfig['consumer_secret'])
                    : '',
            ];

            return $this->testConnection($config);
        } catch (\Exception $e) {
            Log::error('WooCommerce encrypted connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'connected' => false,
                'message' => 'Decryption or connection error',
                'details' => [],
            ];
        }
    }

    /**
     * Encrypt credentials for storage
     *
     * @param  array  $config  Configuration with plain credentials
     * @return array Configuration with encrypted credentials
     */
    public function encryptCredentials(array $config): array
    {
        return [
            'enabled' => $config['enabled'] ?? false,
            'store_url' => $config['store_url'] ?? '',
            'consumer_key' => isset($config['consumer_key'])
                ? Crypt::encrypt($config['consumer_key'])
                : null,
            'consumer_secret' => isset($config['consumer_secret'])
                ? Crypt::encrypt($config['consumer_secret'])
                : null,
            'last_tested_at' => now()->toIso8601String(),
        ];
    }
}
