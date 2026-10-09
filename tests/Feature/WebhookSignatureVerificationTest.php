<?php

use App\Jobs\ProcessOrderWebhookJob;
use App\Models\Shop;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;

/**
 * These endpoints are public (no auth, no CSRF) and create orders, so signature
 * verification is the only thing standing between a shop UUID and forged sales
 * data. Verification must fail CLOSED — a shop with no configured secret must be
 * rejected, never accepted unverified.
 */
beforeEach(function () {
    Queue::fake();
});

/**
 * Build a shop with an encrypted webhook secret for the given platform.
 */
function shopWithWebhookSecret(string $platform, string $secret): Shop
{
    $shop = Shop::factory()->create();

    $shop->setIntegrationConfig($platform, [
        'webhook_secret' => Crypt::encrypt($secret),
    ]);
    $shop->save();

    return $shop->fresh();
}

function postWooWebhook(Shop $shop, string $payload, ?string $signature, string $topic = 'order.updated')
{
    $server = [
        'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
        'CONTENT_TYPE' => 'application/json',
    ];

    if ($signature !== null) {
        $server['HTTP_X_WC_WEBHOOK_SIGNATURE'] = $signature;
    }

    return test()->call('POST', "/api/webhooks/woocommerce/{$shop->uuid}", [], [], [], $server, $payload);
}

// --- WooCommerce: the fail-open regression ---

it('rejects a woocommerce webhook when the shop has no configured secret', function () {
    // Regression: this previously skipped verification entirely and created orders
    // from unauthenticated payloads.
    $shop = Shop::factory()->create();
    $payload = json_encode(['id' => 4242, 'status' => 'processing']);

    postWooWebhook($shop, $payload, 'any-signature-at-all')
        ->assertStatus(400);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});

it('rejects a woocommerce webhook with no signature header', function () {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    $payload = json_encode(['id' => 4242]);

    postWooWebhook($shop, $payload, null)
        ->assertStatus(400);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});

it('rejects a woocommerce webhook whose signature does not match the body', function () {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    $payload = json_encode(['id' => 4242, 'status' => 'processing']);
    $signedForOtherBody = base64_encode(hash_hmac('sha256', '{"id":9999}', 'woo-secret', true));

    postWooWebhook($shop, $payload, $signedForOtherBody)
        ->assertStatus(401);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});

it('rejects a woocommerce webhook signed with the wrong secret', function () {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    $payload = json_encode(['id' => 4242]);
    $wrongSignature = base64_encode(hash_hmac('sha256', $payload, 'not-the-secret', true));

    postWooWebhook($shop, $payload, $wrongSignature)
        ->assertStatus(401);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});

it('accepts a correctly signed woocommerce webhook and queues it', function () {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    $payload = json_encode(['id' => 4242, 'status' => 'processing']);
    $signature = base64_encode(hash_hmac('sha256', $payload, 'woo-secret', true));

    postWooWebhook($shop, $payload, $signature)
        ->assertStatus(200);

    Queue::assertPushed(ProcessOrderWebhookJob::class);
});

// --- Shopify: already fails closed; lock the behaviour in ---

it('rejects a shopify webhook when the shop has no configured secret', function () {
    $shop = Shop::factory()->create();
    $payload = json_encode(['id' => 777]);

    test()->call('POST', "/api/webhooks/shopify/{$shop->uuid}", [], [], [], [
        'HTTP_X_SHOPIFY_HMAC_SHA256' => 'any-signature',
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/updated',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});

it('accepts a correctly signed shopify webhook and queues it', function () {
    $shop = shopWithWebhookSecret('shopify', 'shopify-secret');
    $payload = json_encode(['id' => 777, 'financial_status' => 'paid']);
    $signature = base64_encode(hash_hmac('sha256', $payload, 'shopify-secret', true));

    test()->call('POST', "/api/webhooks/shopify/{$shop->uuid}", [], [], [], [
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/updated',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(200);

    Queue::assertPushed(ProcessOrderWebhookJob::class);
});

// --- Abandoned Cart Recovery plugin events must never be treated as orders ---

it('never sends abandoned-cart plugin events to the order upsert', function (string $topic, string $resource) {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    // A cart payload whose id collides with a real order number.
    $payload = json_encode(['id' => 4242, 'webhook_resource' => $resource, 'email_id' => 'x@example.com']);
    $signature = base64_encode(hash_hmac('sha256', $payload, 'woo-secret', true));

    test()->call('POST', "/api/webhooks/woocommerce/{$shop->uuid}", [], [], [], [
        'HTTP_X_WC_WEBHOOK_TOPIC' => $topic,
        'HTTP_X_WC_WEBHOOK_RESOURCE' => $resource,
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
})->with([
    ['acr_cart.cutoff', 'acr_cart'],
    ['acr_cart.recovered', 'acr_cart'],
    ['acr_email.sent', 'acr_email'],
    ['acr_link.clicked', 'acr_link'],
]);

it('still verifies the signature of abandoned-cart plugin events', function () {
    $shop = shopWithWebhookSecret('woocommerce', 'woo-secret');
    $payload = json_encode(['id' => 1]);

    test()->call('POST', "/api/webhooks/woocommerce/{$shop->uuid}", [], [], [], [
        'HTTP_X_WC_WEBHOOK_TOPIC' => 'acr_cart.cutoff',
        'HTTP_X_WC_WEBHOOK_RESOURCE' => 'acr_cart',
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => 'forged',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(401);
});
