<?php

use App\Services\Integration\ShopifyService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);
beforeEach(function () {
    $this->service = new ShopifyService;
});

test('test connection succeeds with valid credentials', function () {
    Http::fake([
        '*/admin/api/2024-01/shop.json' => Http::response([
            'shop' => [
                'name' => 'Test Shopify Store',
                'email' => 'test@example.com',
                'currency' => 'USD',
                'domain' => 'test-store.myshopify.com',
            ],
        ], 200),
    ]);

    $result = $this->service->testConnection([
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    expect($result['connected'])->toBeTrue()
        ->and($result['message'])->toBe('Successfully connected to Shopify')
        ->and($result['details']['store_name'])->toBe('Test Shopify Store')
        ->and($result['details']['currency'])->toBe('USD');
});

test('test connection normalizes shop domain', function () {
    Http::fake([
        '*/admin/api/2024-01/shop.json' => Http::response([
            'shop' => ['name' => 'Test Store', 'currency' => 'USD'],
        ], 200),
    ]);

    // Test with various domain formats
    $result1 = $this->service->testConnection([
        'shop_domain' => 'test-store',
        'access_token' => 'shpat_test',
    ]);

    expect($result1['connected'])->toBeTrue();

    $result2 = $this->service->testConnection([
        'shop_domain' => 'https://test-store.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    expect($result2['connected'])->toBeTrue();
});

test('test connection fails with invalid credentials', function () {
    Http::fake([
        '*/admin/api/2024-01/shop.json' => Http::response([], 401),
    ]);

    $result = $this->service->testConnection([
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'invalid_token',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toContain('Failed to connect');
});

test('test connection returns false when missing credentials', function () {
    $result = $this->service->testConnection([
        'shop_domain' => '',
        'access_token' => '',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toBe('Missing required credentials');
});

test('test connection handles exceptions gracefully', function () {
    Http::fake(function () {
        throw new \Exception('Network error');
    });

    $result = $this->service->testConnection([
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'shpat_test',
    ]);

    expect($result['connected'])->toBeFalse()
        ->and($result['message'])->toContain('Connection error');
});

test('encrypt credentials encrypts sensitive data', function () {
    $config = [
        'enabled' => true,
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => 'shpat_test123',
    ];

    $encrypted = $this->service->encryptCredentials($config);

    expect($encrypted['enabled'])->toBeTrue()
        ->and($encrypted['shop_domain'])->toBe('test-store.myshopify.com')
        ->and($encrypted['access_token'])->not->toBe('shpat_test123')
        ->and($encrypted)->toHaveKey('last_tested_at');

    // Verify decryption works
    $decryptedToken = Crypt::decrypt($encrypted['access_token']);

    expect($decryptedToken)->toBe('shpat_test123');
});

test('test connection with encrypted credentials', function () {
    Http::fake([
        '*/admin/api/2024-01/shop.json' => Http::response([
            'shop' => ['name' => 'Test Store', 'currency' => 'USD'],
        ], 200),
    ]);

    $encryptedConfig = [
        'shop_domain' => 'test-store.myshopify.com',
        'access_token' => Crypt::encrypt('shpat_test'),
    ];

    $result = $this->service->testConnectionWithEncrypted($encryptedConfig);

    expect($result['connected'])->toBeTrue();
});
