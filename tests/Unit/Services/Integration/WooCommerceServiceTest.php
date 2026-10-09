<?php

use App\Services\Integration\WooCommerceService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);
beforeEach(function () {
    $this->service = new WooCommerceService;
});

test('test connection succeeds with valid credentials', function () {
    Http::fake([
        '*/wp-json/wc/v3/system_status' => Http::response([
            'settings' => ['title' => 'Test Store', 'currency' => 'USD'],
            'environment' => ['version' => '8.5.2'],
        ], 200),
    ]);

    $result = $this->service->testConnection([
        'store_url' => 'https://test-store.com',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_test',
    ]);

    expect($result['connected'])->toBeTrue()
        ->and($result['message'])->toBe('Successfully connected to WooCommerce')
        ->and($result['details']['store_name'])->toBe('Test Store')
        ->and($result['details']['currency'])->toBe('USD');
});

test('test connection fails with invalid credentials', function () {
    Http::fake([
        '*/wp-json/wc/v3/system_status' => Http::response([], 401),
    ]);

    $result = $this->service->testConnection([
        'store_url' => 'https://test-store.com',
        'consumer_key' => 'invalid_key',
        'consumer_secret' => 'invalid_secret',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toContain('Failed to connect');
});

test('test connection returns false when missing credentials', function () {
    $result = $this->service->testConnection([
        'store_url' => '',
        'consumer_key' => '',
        'consumer_secret' => '',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toBe('Missing required credentials');
});

test('test connection handles exceptions gracefully', function () {
    Http::fake(function () {
        throw new \Exception('Network error');
    });

    $result = $this->service->testConnection([
        'store_url' => 'https://test-store.com',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_test',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toContain('Connection error');
});

test('encrypt credentials encrypts sensitive data', function () {
    $config = [
        'enabled' => true,
        'store_url' => 'https://test-store.com',
        'consumer_key' => 'ck_test123',
        'consumer_secret' => 'cs_test456',
    ];

    $encrypted = $this->service->encryptCredentials($config);

    expect($encrypted['enabled'])->toBeTrue()
        ->and($encrypted['store_url'])->toBe('https://test-store.com')
        ->and($encrypted['consumer_key'])->not->toBe('ck_test123')
        ->and($encrypted['consumer_secret'])->not->toBe('cs_test456')
        ->and($encrypted)->toHaveKey('last_tested_at');

    // Verify decryption works
    $decryptedKey = Crypt::decrypt($encrypted['consumer_key']);
    $decryptedSecret = Crypt::decrypt($encrypted['consumer_secret']);

    expect($decryptedKey)->toBe('ck_test123')
        ->and($decryptedSecret)->toBe('cs_test456');
});

test('test connection with encrypted credentials', function () {
    Http::fake([
        '*/wp-json/wc/v3/system_status' => Http::response([
            'settings' => ['title' => 'Test Store', 'currency' => 'USD'],
            'environment' => ['version' => '8.5.2'],
        ], 200),
    ]);

    $encryptedConfig = [
        'store_url' => 'https://test-store.com',
        'consumer_key' => Crypt::encrypt('ck_test'),
        'consumer_secret' => Crypt::encrypt('cs_test'),
    ];

    $result = $this->service->testConnectionWithEncrypted($encryptedConfig);

    expect($result['connected'])->toBeTrue();
});
