#!/usr/bin/env php
<?php

/**
 * Integration Test Runner
 *
 * Run with: php tests/run-integration-tests.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Shop;
use App\Services\Integration\ShopifyService;
use App\Services\Integration\WooCommerceService;
use Illuminate\Support\Facades\Crypt;

echo "\n=== E-commerce Integration Test Script ===\n\n";

// Test 1: WooCommerce Service - Encryption
echo "Test 1: WooCommerce Encryption\n";
echo "--------------------------------\n";
$wooService = app(WooCommerceService::class);
$wooCredentials = [
    'enabled' => true,
    'store_url' => 'https://test-store.com',
    'consumer_key' => 'ck_test_key_123',
    'consumer_secret' => 'cs_test_secret_456',
];

$encryptedWoo = $wooService->encryptCredentials($wooCredentials);
echo "Original Key: {$wooCredentials['consumer_key']}\n";
echo 'Encrypted: ' . (strlen($encryptedWoo['consumer_key']) > 50 ? 'YES' : 'NO') . "\n";
$decryptedKey = Crypt::decrypt($encryptedWoo['consumer_key']);
echo 'Decrypted matches: ' . ($decryptedKey === $wooCredentials['consumer_key'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Shopify Service - Encryption
echo "Test 2: Shopify Encryption\n";
echo "--------------------------------\n";
$shopifyService = app(ShopifyService::class);
$shopifyCredentials = [
    'enabled' => true,
    'shop_domain' => 'test-store.myshopify.com',
    'access_token' => 'shpat_test_token_789',
];

$encryptedShopify = $shopifyService->encryptCredentials($shopifyCredentials);
echo "Original Token: {$shopifyCredentials['access_token']}\n";
echo 'Encrypted: ' . (strlen($encryptedShopify['access_token']) > 50 ? 'YES' : 'NO') . "\n";
$decryptedToken = Crypt::decrypt($encryptedShopify['access_token']);
echo 'Decrypted matches: ' . ($decryptedToken === $shopifyCredentials['access_token'] ? 'PASS' : 'FAIL') . "\n\n";

// Test 3: Shop Model - Integration Helper Methods
echo "Test 3: Shop Model Integration Methods\n";
echo "---------------------------------------\n";
$testShop = Shop::first();
if ($testShop) {
    // Set a test integration
    $testShop->setIntegrationConfig('woocommerce', [
        'enabled' => true,
        'store_url' => 'https://demo.com',
        'consumer_key' => 'encrypted_key',
    ]);

    $config = $testShop->getIntegrationConfig('woocommerce');
    echo 'Set integration config: ' . (isset($config['store_url']) ? 'PASS' : 'FAIL') . "\n";

    $enabled = $testShop->getEnabledIntegrations();
    echo 'Get enabled integrations: ' . (in_array('woocommerce', $enabled) ? 'PASS' : 'FAIL') . "\n";

    $connected = $testShop->isIntegrationConnected('woocommerce');
    echo 'Check if connected: ' . ($connected ? 'PASS' : 'FAIL') . "\n";

    $status = $testShop->getConnectionStatus('woocommerce');
    echo 'Get connection status: ' . json_encode($status) . "\n";
} else {
    echo "No shops found - skipping model tests\n";
}
echo "\n";

// Test 4: Create Shop with WooCommerce Integration
echo "Test 4: Create Shop with WooCommerce Integration\n";
echo "-------------------------------------------------\n";
try {
    $shopData = [
        'name' => 'Test Integration Shop ' . now()->timestamp,
        'code' => 'TEST-INT-' . now()->timestamp,
        'status' => 'active',
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://demo-store.com',
                'consumer_key' => 'ck_demo_key',
                'consumer_secret' => 'cs_demo_secret',
            ],
        ],
    ];

    $action = app(\App\Actions\Shop\CreateShopAction::class);
    $newShop = $action->execute($shopData);

    echo "Shop created: {$newShop->name}\n";
    echo "Shop code: {$newShop->code}\n";

    $wooConfig = $newShop->getIntegrationConfig('woocommerce');
    echo 'WooCommerce configured: ' . (! empty($wooConfig) ? 'YES' : 'NO') . "\n";
    echo 'Store URL saved: ' . ($wooConfig['store_url'] ?? 'NOT FOUND') . "\n";
    echo 'Credentials encrypted: ' . (strlen($wooConfig['consumer_key'] ?? '') > 50 ? 'YES' : 'NO') . "\n";

    // Clean up
    $newShop->delete();
    echo "Test shop cleaned up\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
echo "\n";

// Test 5: Create Shop with Shopify Integration
echo "Test 5: Create Shop with Shopify Integration\n";
echo "---------------------------------------------\n";
try {
    $shopData = [
        'name' => 'Test Shopify Shop ' . now()->timestamp,
        'code' => 'TEST-SHOP-' . now()->timestamp,
        'status' => 'active',
        'integrations' => [
            'shopify' => [
                'enabled' => true,
                'shop_domain' => 'demo-store.myshopify.com',
                'access_token' => 'shpat_demo_token',
            ],
        ],
    ];

    $action = app(\App\Actions\Shop\CreateShopAction::class);
    $newShop = $action->execute($shopData);

    echo "Shop created: {$newShop->name}\n";

    $shopifyConfig = $newShop->getIntegrationConfig('shopify');
    echo 'Shopify configured: ' . (! empty($shopifyConfig) ? 'YES' : 'NO') . "\n";
    echo 'Shop domain saved: ' . ($shopifyConfig['shop_domain'] ?? 'NOT FOUND') . "\n";
    echo 'Token encrypted: ' . (strlen($shopifyConfig['access_token'] ?? '') > 50 ? 'YES' : 'NO') . "\n";

    // Clean up
    $newShop->delete();
    echo "Test shop cleaned up\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Update Shop Integration
echo "Test 6: Update Shop Integration\n";
echo "--------------------------------\n";
try {
    // Create a test shop
    $testShop = Shop::create([
        'name' => 'Update Test Shop ' . now()->timestamp,
        'code' => 'UPDATE-' . now()->timestamp,
        'status' => 'active',
    ]);

    // Update with integration
    $updateAction = app(\App\Actions\Shop\UpdateShopAction::class);
    $updateData = [
        'integrations' => [
            'woocommerce' => [
                'enabled' => true,
                'store_url' => 'https://updated-store.com',
                'consumer_key' => 'ck_updated_key',
                'consumer_secret' => 'cs_updated_secret',
            ],
        ],
    ];

    $updatedShop = $updateAction->execute($testShop, $updateData);

    echo "Shop updated: {$updatedShop->name}\n";
    $wooConfig = $updatedShop->getIntegrationConfig('woocommerce');
    echo 'Integration added: ' . (! empty($wooConfig) ? 'YES' : 'NO') . "\n";
    echo 'Store URL updated: ' . ($wooConfig['store_url'] ?? 'NOT FOUND') . "\n";

    // Clean up
    $updatedShop->delete();
    echo "Test shop cleaned up\n";
} catch (\Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
echo "\n";

echo "=== All Tests Completed ===\n\n";
