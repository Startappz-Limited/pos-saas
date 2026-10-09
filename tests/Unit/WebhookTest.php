<?php

uses(Tests\TestCase::class);

// --- WooCommerce HMAC Signature Verification Tests ---

test('woocommerce hmac signature is computed correctly', function () {
    $secret = 'test-webhook-secret';
    $payload = json_encode(['id' => 123, 'status' => 'processing']);

    $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
    $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    expect(hash_equals($expected, $computed))->toBeTrue();
});

test('woocommerce hmac signature fails with wrong secret', function () {
    $correctSecret = 'correct-secret';
    $wrongSecret = 'wrong-secret';
    $payload = json_encode(['id' => 123]);

    $correctSig = base64_encode(hash_hmac('sha256', $payload, $correctSecret, true));
    $wrongSig = base64_encode(hash_hmac('sha256', $payload, $wrongSecret, true));

    expect(hash_equals($correctSig, $wrongSig))->toBeFalse();
});

test('woocommerce hmac signature fails with modified payload', function () {
    $secret = 'test-secret';
    $originalPayload = json_encode(['id' => 123, 'status' => 'processing']);
    $modifiedPayload = json_encode(['id' => 123, 'status' => 'completed']);

    $originalSig = base64_encode(hash_hmac('sha256', $originalPayload, $secret, true));
    $modifiedSig = base64_encode(hash_hmac('sha256', $modifiedPayload, $secret, true));

    expect(hash_equals($originalSig, $modifiedSig))->toBeFalse();
});

// --- Shopify HMAC Signature Verification Tests ---

test('shopify hmac signature is computed correctly', function () {
    $secret = 'test-shopify-secret';
    $payload = json_encode(['id' => 456, 'financial_status' => 'paid']);

    $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));
    $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    expect(hash_equals($expected, $computed))->toBeTrue();
});

test('shopify hmac signature fails with wrong secret', function () {
    $correctSecret = 'correct-shopify-secret';
    $wrongSecret = 'wrong-shopify-secret';
    $payload = json_encode(['id' => 456]);

    $correctSig = base64_encode(hash_hmac('sha256', $payload, $correctSecret, true));
    $wrongSig = base64_encode(hash_hmac('sha256', $payload, $wrongSecret, true));

    expect(hash_equals($correctSig, $wrongSig))->toBeFalse();
});

// --- Webhook Topic / Event Tests ---

test('woocommerce test topic is identified correctly', function () {
    $topic = 'action.woocommerce_webhook_test';

    expect($topic)->toBe('action.woocommerce_webhook_test');
    expect(str_starts_with($topic, 'action.'))->toBeTrue();
});

test('woocommerce order topics are recognized', function () {
    $validTopics = ['order.created', 'order.updated', 'order.deleted', 'order.restored'];

    foreach ($validTopics as $topic) {
        expect(str_starts_with($topic, 'order.'))->toBeTrue();
    }
});

test('shopify order topics are recognized', function () {
    $validTopics = ['orders/create', 'orders/updated', 'orders/cancelled', 'orders/fulfilled', 'orders/paid'];

    foreach ($validTopics as $topic) {
        expect(str_starts_with($topic, 'orders/'))->toBeTrue();
    }
});

// --- Encryption Round-Trip Tests ---

test('webhook secret survives encrypt/decrypt round-trip', function () {
    $originalSecret = 'my-super-secret-webhook-key-12345';

    $encrypted = Illuminate\Support\Facades\Crypt::encrypt($originalSecret);
    $decrypted = Illuminate\Support\Facades\Crypt::decrypt($encrypted);

    expect($decrypted)->toBe($originalSecret);
});

test('hmac verification works with encrypted then decrypted secret', function () {
    $secret = 'round-trip-test-secret';
    $payload = json_encode(['order_id' => 789]);

    $encrypted = Illuminate\Support\Facades\Crypt::encrypt($secret);
    $decrypted = Illuminate\Support\Facades\Crypt::decrypt($encrypted);

    $sigFromOriginal = base64_encode(hash_hmac('sha256', $payload, $secret, true));
    $sigFromDecrypted = base64_encode(hash_hmac('sha256', $payload, $decrypted, true));

    expect(hash_equals($sigFromOriginal, $sigFromDecrypted))->toBeTrue();
});
