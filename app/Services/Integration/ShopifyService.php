<?php

namespace App\Services\Integration;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ShopifyService
{
    /**
     * Test connection to Shopify store
     *
     * @param  array  $config  Configuration with shop_domain and access_token
     * @return array{connected: bool, message: string, details: array}
     */
    public function testConnection(array $config): array
    {
        try {
            $shopDomain = $config['shop_domain'] ?? '';
            $accessToken = $config['access_token'] ?? '';

            if (empty($shopDomain) || empty($accessToken)) {
                return [
                    'connected' => false,
                    'message' => 'Missing required credentials',
                    'details' => [],
                ];
            }

            // Normalize shop domain
            $shopDomain = str_replace(['https://', 'http://'], '', $shopDomain);
            if (! str_ends_with($shopDomain, '.myshopify.com')) {
                $shopDomain .= '.myshopify.com';
            }

            // Test connection by fetching shop info
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                ])
                ->get("https://{$shopDomain}/admin/api/2024-01/shop.json");

            if ($response->successful()) {
                $data = $response->json();
                $shop = $data['shop'] ?? [];

                return [
                    'connected' => true,
                    'message' => 'Successfully connected to Shopify',
                    'details' => [
                        'store_name' => $shop['name'] ?? 'Unknown',
                        'email' => $shop['email'] ?? 'Unknown',
                        'currency' => $shop['currency'] ?? 'Unknown',
                        'domain' => $shop['domain'] ?? $shopDomain,
                    ],
                ];
            }

            return [
                'connected' => false,
                'message' => 'Failed to connect: ' . $response->status(),
                'details' => ['status_code' => $response->status()],
            ];
        } catch (\Exception $e) {
            Log::error('Shopify connection test failed', [
                'error' => $e->getMessage(),
                'shop_domain' => $config['shop_domain'] ?? 'N/A',
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
                'shop_domain' => $encryptedConfig['shop_domain'] ?? '',
                'access_token' => isset($encryptedConfig['access_token'])
                    ? Crypt::decrypt($encryptedConfig['access_token'])
                    : '',
            ];

            return $this->testConnection($config);
        } catch (\Exception $e) {
            Log::error('Shopify encrypted connection test failed', [
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
            'shop_domain' => $config['shop_domain'] ?? '',
            'access_token' => isset($config['access_token'])
                ? Crypt::encrypt($config['access_token'])
                : null,
            'last_tested_at' => now()->toIso8601String(),
        ];
    }
}
