<?php

use App\Models\Shop;

uses(Tests\TestCase::class);

test('get integration config returns correct configuration', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
            ],
        ],
    ];

    $config = $shop->getIntegrationConfig('woocommerce');

    expect($config)->toBeArray()
        ->and($config['enabled'])->toBeTrue()
        ->and($config['store_url'])->toBe('https://test.com');
});

test('get integration config returns null when not set', function () {
    $shop = new Shop;
    $shop->settings = [];

    $config = $shop->getIntegrationConfig('woocommerce');

    expect($config)->toBeNull();
});

test('set integration config updates settings', function () {
    $shop = new Shop;
    $shop->settings = [];

    $shop->setIntegrationConfig('woocommerce', [
        'enabled' => true,
        'store_url' => 'https://test.com',
    ]);

    expect($shop->settings['integrations']['woocommerce'])->toBeArray()
        ->and($shop->settings['integrations']['woocommerce']['enabled'])->toBeTrue();
});

test('set integration config preserves other integrations', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'shopify' => ['enabled' => true],
        ],
    ];

    $shop->setIntegrationConfig('woocommerce', [
        'enabled' => true,
        'store_url' => 'https://test.com',
    ]);

    expect($shop->settings['integrations']['shopify'])->toBeArray()
        ->and($shop->settings['integrations']['woocommerce'])->toBeArray();
});

test('is integration connected returns true when enabled with credentials', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
        ],
    ];

    expect($shop->isIntegrationConnected('woocommerce'))->toBeTrue();
});

test('is integration connected returns false when disabled', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => false,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
            ],
        ],
    ];

    expect($shop->isIntegrationConnected('woocommerce'))->toBeFalse();
});

test('is integration connected returns false when missing credentials', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
            ],
        ],
    ];

    expect($shop->isIntegrationConnected('woocommerce'))->toBeFalse();
});

test('is integration connected works for shopify', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'shopify' => [
                'enabled' => true,
                'shop_domain' => 'test.myshopify.com',
                'access_token' => 'shpat_test',
            ],
        ],
    ];

    expect($shop->isIntegrationConnected('shopify'))->toBeTrue();
});

test('get connection status returns complete status', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://test.com',
                'consumer_key' => 'ck_test',
                'consumer_secret' => 'cs_test',
                'last_tested_at' => '2026-02-13T10:00:00Z',
            ],
        ],
    ];

    $status = $shop->getConnectionStatus('woocommerce');

    expect($status)->toBeArray()
        ->and($status['connected'])->toBeTrue()
        ->and($status['enabled'])->toBeTrue()
        ->and($status['last_tested_at'])->toBe('2026-02-13T10:00:00Z');
});

test('get enabled integrations returns all enabled platforms', function () {
    $shop = new Shop;
    $shop->settings = [
        'integrations' => [
            'woocommerce' => ['enabled' => true],
            'shopify' => ['enabled' => false],
        ],
    ];

    $enabled = $shop->getEnabledIntegrations();

    expect($enabled)->toBeArray()
        ->and($enabled)->toContain('woocommerce')
        ->and($enabled)->not->toContain('shopify')
        ->and($enabled)->toHaveCount(1);
});

test('get enabled integrations returns empty array when no integrations', function () {
    $shop = new Shop;
    $shop->settings = [];

    $enabled = $shop->getEnabledIntegrations();

    expect($enabled)->toBeArray()
        ->and($enabled)->toBeEmpty();
});
