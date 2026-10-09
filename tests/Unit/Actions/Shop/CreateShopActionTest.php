<?php

use App\Actions\Shop\CreateShopAction;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('creates shop without integration config', function () {
    $action = app(CreateShopAction::class);

    $data = [
        'name' => 'Test Shop',
        'code' => 'TEST-001',
        'status' => 'active',
    ];

    $shop = $action->execute($data);

    expect($shop)->toBeInstanceOf(Shop::class)
        ->and($shop->name)->toBe('Test Shop')
        ->and($shop->code)->toBe('TEST-001');
});

test('creates shop with woocommerce integration', function () {
    $action = app(CreateShopAction::class);

    $data = [
        'name' => 'Test Shop',
        'code' => 'TEST-002',
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
        ],
    ];

    $shop = $action->execute($data);

    expect($shop->settings)->toHaveKey('integrations')
        ->and($shop->settings['integrations'])->toHaveKey('woocommerce')
        ->and($shop->settings['integrations']['woocommerce']['enabled'])->toBeTrue()
        ->and($shop->settings['integrations']['woocommerce']['store_url'])->toBe('https://test.com');

    // Verify credentials are encrypted
    $encryptedKey = $shop->settings['integrations']['woocommerce']['consumer_key'];
    expect($encryptedKey)->not->toBe('ck_test');

    $decryptedKey = Crypt::decrypt($encryptedKey);
    expect($decryptedKey)->toBe('ck_test');
});

test('creates shop with shopify integration', function () {
    $action = app(CreateShopAction::class);

    $data = [
        'name' => 'Test Shop',
        'code' => 'TEST-003',
        'integrations' => [
            'shopify' => [
                'enabled' => true,
                'shop_domain' => 'test.myshopify.com',
                'access_token' => 'shpat_test',
            ],
        ],
    ];

    $shop = $action->execute($data);

    expect($shop->settings)->toHaveKey('integrations')
        ->and($shop->settings['integrations'])->toHaveKey('shopify')
        ->and($shop->settings['integrations']['shopify']['enabled'])->toBeTrue();

    // Verify token is encrypted
    $encryptedToken = $shop->settings['integrations']['shopify']['access_token'];
    expect($encryptedToken)->not->toBe('shpat_test');
});

test('creates shop with multiple integrations', function () {
    $action = app(CreateShopAction::class);

    $data = [
        'name' => 'Test Shop',
        'code' => 'TEST-004',
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
            'shopify' => [
                'enabled' => true,
                'shop_domain' => 'test.myshopify.com',
                'access_token' => 'shpat_test',
            ],
        ],
    ];

    $shop = $action->execute($data);

    expect($shop->settings['integrations'])->toHaveKey('woocommerce')
        ->and($shop->settings['integrations'])->toHaveKey('shopify');
});

test('ignores empty integration config', function () {
    $action = app(CreateShopAction::class);

    $data = [
        'name' => 'Test Shop',
        'code' => 'TEST-005',
        'integrations' => [
            'woocommerce' => [
                'enabled' => false,
            ],
        ],
    ];

    $shop = $action->execute($data);

    expect($shop->settings['integrations'] ?? [])->toBeEmpty();
});
