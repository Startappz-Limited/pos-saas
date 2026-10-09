<?php

use App\Models\Shop;
use Illuminate\Support\Facades\Crypt;

/**
 * Shared fixtures for the abandoned-cart tests.
 */
function acrShop(array $attributes = [], string $secret = 'woo-secret'): Shop
{
    $shop = Shop::factory()->create($attributes);

    $shop->setIntegrationConfig('woocommerce', [
        'enabled' => true,
        'store_url' => 'https://shop.test',
        'consumer_key' => Crypt::encrypt('ck_test'),
        'consumer_secret' => Crypt::encrypt('cs_test'),
        'webhook_secret' => Crypt::encrypt($secret),
    ]);
    $shop->save();

    return $shop->fresh();
}

/**
 * A cut-off payload shaped like the plugin's WebhookTopics::build().
 *
 * @return array<string, mixed>
 */
function acrCutoffPayload(array $overrides = []): array
{
    return array_merge([
        'webhook_id' => 7,
        'webhook_action' => 'cutoff',
        'webhook_resource' => 'acr_cart',
        'webhook_resource_id' => 501,
        'id' => 501,
        'product_details' => [
            [
                'product_id' => 11,
                'variation_id' => 0,
                'product_name' => 'Yoga Mat',
                'sku' => '',
                'quantity' => 2,
                'price' => '1160.00',
                'image' => 'https://shop.test/wp-content/uploads/mat.jpg',
                'line_subtotal' => '2000.00',
                'total' => '2000.00',
                'total_tax' => '320.00',
            ],
        ],
        'user_id' => 0,
        'user_type' => 'GUEST',
        'timestamp' => '1790000000',
        'billing_first_name' => 'Jane',
        'billing_last_name' => 'Wanjiku',
        'billing_country' => 'KE',
        'billing_zipcode' => '',
        'email_id' => 'jane@example.com',
        'phone' => '0712 345 678',
        'language' => 'en',
        'status' => 'abandoned',
        'captured_by' => 'checkout',
        'cart_total' => '2320.00',
        'cart_status' => 'abandoned',
        'capture_source' => 'checkout',
        'abandoned_at' => '2026-09-26T08:00:00Z',
        'customer_name' => 'Jane Wanjiku',
        'coupon_code' => '',
        'checkout_link' => 'https://shop.test/?acr_recover=501&token=SECRET-TOKEN',
        'currency' => 'KES',
        'total' => '2320.00',
        'tax_total' => '320.00',
    ], $overrides);
}

/**
 * POST a signed plugin webhook through the real WooCommerceController endpoint.
 */
function postAcrWebhook(Shop $shop, string $topic, array $payload, string $secret = 'woo-secret')
{
    $body = json_encode($payload);
    $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

    return test()->call('POST', "/api/webhooks/woocommerce/{$shop->uuid}", [], [], [], [
        'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
        'HTTP_X_WC_WEBHOOK_RESOURCE' => strtok($topic, '.'),
        'HTTP_X_WC_WEBHOOK_EVENT' => substr($topic, strpos($topic, '.') + 1),
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}
