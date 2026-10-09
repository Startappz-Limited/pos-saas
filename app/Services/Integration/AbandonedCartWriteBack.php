<?php

namespace App\Services\Integration;

use App\Models\AbandonedCart;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * POS → website: tells the Abandoned Cart Recovery plugin what happened to a
 * cart in the shop, so it stops sending reminders.
 *
 * Talks to the plugin's `wc-acr/v1` REST namespace with the shop's WooCommerce
 * REST API key over HTTP Basic auth — the same credentials registerWebhooks()
 * uses. The key must be Read/Write.
 */
class AbandonedCartWriteBack
{
    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * Set the cart's status on the website.
     *
     * `retryable` is true only for failures a later attempt could fix (network
     * errors, 5xx, 429). A 404/409/422 is an answer, not an outage.
     *
     * @return array{success: bool, retryable: bool, status?: int, code?: string|null, message?: string, data?: array<string, mixed>}
     */
    public function setStatus(AbandonedCart $cart, string $status, ?string $reference = null, ?string $note = null): array
    {
        $client = $this->client($cart);

        if (isset($client['error'])) {
            return ['success' => false, 'retryable' => false, 'message' => $client['error']];
        }

        // The plugin rejects a reference over 191 or a note over 1000 characters.
        $body = array_filter([
            'status' => $status,
            'reference' => $reference !== null ? mb_substr($reference, 0, 191) : null,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->withBasicAuth($client['key'], $client['secret'])
                ->post("{$client['base']}/carts/".rawurlencode($cart->platform_cart_id).'/status', $body);
        } catch (ConnectionException $e) {
            Log::warning('Abandoned-cart write-back could not reach the website', [
                'cart_id' => $cart->id,
                'shop_id' => $cart->shop_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'retryable' => true, 'message' => 'Could not reach the website.'];
        }

        if ($response->successful()) {
            return ['success' => true, 'retryable' => false, 'status' => $response->status(), 'data' => (array) $response->json()];
        }

        // Errors are WP_Error JSON: {code, message, data: {status, …}}.
        $code = is_string($response->json('code')) ? $response->json('code') : null;

        $message = match ($response->status()) {
            401, 403 => 'The website refused the POS\'s API key. The store must be served over HTTPS (WooCommerce ignores the key over plain HTTP) and the key must have Read/Write access.',
            404 => 'The website no longer has this cart, or the Abandoned Cart Recovery plugin is not installed.',
            409 => 'The website says this cart is already closed'
                .(is_string($response->json('data.cart_status')) ? ' ('.$response->json('data.cart_status').')' : '')
                .'; it was turned into an order there.',
            422 => 'The website rejected the request: '.(is_string($response->json('message')) ? $response->json('message') : 'invalid status'),
            default => 'The website returned HTTP '.$response->status().'.',
        };

        Log::warning('Abandoned-cart write-back failed', [
            'cart_id' => $cart->id,
            'shop_id' => $cart->shop_id,
            'status' => $response->status(),
            'code' => $code,
            'requested' => $status,
        ]);

        return [
            'success' => false,
            'retryable' => $response->serverError() || $response->status() === 429,
            'status' => $response->status(),
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @return array{base?: string, key?: string, secret?: string, error?: string}
     */
    private function client(AbandonedCart $cart): array
    {
        $cart->loadMissing('shop');
        $config = $cart->shop?->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['error' => 'WooCommerce integration is not enabled for this shop.'];
        }

        if (empty($config['store_url']) || empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['error' => 'WooCommerce API credentials are incomplete.'];
        }

        try {
            return [
                'base' => rtrim($config['store_url'], '/').'/wp-json/wc-acr/v1',
                'key' => Crypt::decrypt($config['consumer_key']),
                'secret' => Crypt::decrypt($config['consumer_secret']),
            ];
        } catch (\Throwable) {
            return ['error' => 'WooCommerce API credentials could not be decrypted.'];
        }
    }
}
