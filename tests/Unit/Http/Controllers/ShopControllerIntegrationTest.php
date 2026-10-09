<?php

use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

test('integration returns success for valid woocommerce credentials', function () {
    $this->withoutMiddleware();
    Http::fake([
        '*/wp-json/wc/v3/system_status' => Http::response([
            'settings' => ['title' => 'Test Store', 'currency' => 'USD'],
            'environment' => ['version' => '8.5.2'],
        ], 200),
    ]);

    $this->postJson(route('shops.testIntegration'), [
        'platform' => 'woocommerce',
        'credentials' => [
            'store_url' => 'https://test.com',
            'consumer_key' => 'ck_test',
            'consumer_secret' => 'cs_test',
        ],
    ])
        ->assertOk()
        ->assertJson([
            'connected' => true,
        ])
        ->assertJsonFragment(['message' => 'Successfully connected to WooCommerce']);
});

test('test integration returns success for valid shopify credentials', function () {
    $this->withoutMiddleware();

    Http::fake([
        '*/admin/api/2024-01/shop.json' => Http::response([
            'shop' => ['name' => 'Test Store', 'currency' => 'USD'],
        ], 200),
    ]);

    $this->postJson(route('shops.testIntegration'), [
        'platform' => 'shopify',
        'credentials' => [
            'shop_domain' => 'test.myshopify.com',
            'access_token' => 'shpat_test',
        ],
    ])
        ->assertOk()
        ->assertJson([
            'connected' => true,
        ]);
});

test('test integration returns failure for invalid credentials', function () {
    $this->withoutMiddleware();

    Http::fake([
        '*/wp-json/wc/v3/system_status' => Http::response([], 401),
    ]);

    $this->postJson(route('shops.testIntegration'), [
        'platform' => 'woocommerce',
        'credentials' => [
            'store_url' => 'https://test.com',
            'consumer_key' => 'invalid',
            'consumer_secret' => 'invalid',
        ],
    ])
        ->assertOk()
        ->assertJson([
            'connected' => false,
        ]);
});

test('test integration handles unsupported platform', function () {
    $this->withoutMiddleware();

    $this->postJson(route('shops.testIntegration'), [
        'platform' => 'unsupported',
        'credentials' => [],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['platform', 'credentials']);
});
