#!/usr/bin/env php
<?php

/**
 * Product Sync Test Runner
 *
 * Run with: php tests/run-product-sync-tests.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\Shop;

echo "\n=== Product E-commerce Sync Test Script ===\n\n";

// Test 1: SKU Generation
echo "Test 1: SKU Generation\n";
echo "----------------------\n";
$sku1 = Product::generateSku();
$sku2 = Product::generateSku('WC');
$sku3 = Product::generateSku('SH');

echo "Generated SKU (default): {$sku1}\n";
echo "Generated SKU (WC): {$sku2}\n";
echo "Generated SKU (SH): {$sku3}\n";
echo 'SKUs are unique: ' . (($sku1 !== $sku2 && $sku2 !== $sku3) ? 'PASS' : 'FAIL') . "\n\n";

// Test 2: Product Relationships
echo "Test 2: Product Sync Relationships\n";
echo "-----------------------------------\n";
$products = Product::with('ecommerceSyncs')->take(1)->get();
if ($products->isNotEmpty()) {
    $product = $products->first();
    echo "Product: {$product->name} (SKU: {$product->sku})\n";
    echo "Sync records: {$product->ecommerceSyncs->count()}\n";

    $syncedPlatforms = $product->getSyncedPlatforms();
    echo 'Synced platforms: ' . implode(', ', $syncedPlatforms ?: ['none']) . "\n";
} else {
    echo "No products found - create products first\n";
}
echo "\n";

// Test 3: Shop Relationships
echo "Test 3: Shop Sync Relationships\n";
echo "--------------------------------\n";
$shops = Shop::with('ecommerceSyncs')->first();
if ($shops) {
    echo "Shop: {$shops->name}\n";
    echo "Product syncs: {$shops->ecommerceSyncs->count()}\n";
    echo 'Enabled integrations: ' . implode(', ', $shops->getEnabledIntegrations() ?: ['none']) . "\n";
} else {
    echo "No shops found\n";
}
echo "\n";

// Test 4: Sync Model Methods
echo "Test 4: Sync Model Methods\n";
echo "---------------------------\n";
$sync = ProductEcommerceSync::first();
if ($sync) {
    echo "Sync ID: {$sync->id}\n";
    echo "Platform: {$sync->platform}\n";
    echo "Status: {$sync->sync_status}\n";
    echo 'Is Pending: ' . ($sync->isPending() ? 'YES' : 'NO') . "\n";
    echo 'Is Synced: ' . ($sync->isSynced() ? 'YES' : 'NO') . "\n";
    echo 'Has Error: ' . ($sync->hasError() ? 'YES' : 'NO') . "\n";
    echo 'Auto-sync: ' . ($sync->auto_sync ? 'YES' : 'NO') . "\n";
} else {
    echo "No sync records found - sync products first\n";
}
echo "\n";

// Test 5: Query Scopes
echo "Test 5: Query Scopes\n";
echo "--------------------\n";
$wooSyncs = ProductEcommerceSync::platform('woocommerce')->count();
$shopifySyncs = ProductEcommerceSync::platform('shopify')->count();
$syncedCount = ProductEcommerceSync::status('synced')->count();
$pendingCount = ProductEcommerceSync::status('pending')->count();
$errorCount = ProductEcommerceSync::status('error')->count();
$needsSyncCount = ProductEcommerceSync::needsSync()->count();

echo "WooCommerce syncs: {$wooSyncs}\n";
echo "Shopify syncs: {$shopifySyncs}\n";
echo "Synced: {$syncedCount}\n";
echo "Pending: {$pendingCount}\n";
echo "Errors: {$errorCount}\n";
echo "Needs sync: {$needsSyncCount}\n";
echo "\n";

// Test 6: Product Sync Status
echo "Test 6: Product Sync Status\n";
echo "----------------------------\n";
$product = Product::first();
if ($product) {
    echo "Product: {$product->name}\n";
    $shops = Shop::take(2)->get();

    foreach ($shops as $shop) {
        echo "\nShop: {$shop->name}\n";

        foreach (['woocommerce', 'shopify'] as $platform) {
            $isSynced = $product->isSyncedWith($shop->id, $platform);
            $sync = $product->getSyncRecord($shop->id, $platform);

            echo "  {$platform}: " . ($isSynced ? 'SYNCED' : 'NOT SYNCED');
            if ($sync) {
                echo " (Status: {$sync->sync_status})";
            }
            echo "\n";
        }
    }
} else {
    echo "No products found\n";
}
echo "\n";

// Test 7: Create Test Product with Auto-generated SKU
echo "Test 7: Create Product with Auto-generated SKU\n";
echo "-----------------------------------------------\n";
try {
    if (! \App\Models\Category::exists()) {
        echo "No categories found - skipping product creation\n";
    } else {
        $testProduct = Product::create([
            'name' => 'Test Sync Product ' . now()->timestamp,
            'slug' => 'test-sync-product-' . now()->timestamp,
            'description' => 'Test product for e-commerce sync',
            'sku' => Product::generateSku('TEST'),
            'category_id' => \App\Models\Category::first()->id,
            'cost_price' => 10.00,
            'selling_price' => 15.99,
            'stock_quantity' => 100,
            'status' => 'active',
        ]);

        echo "Product created: {$testProduct->name}\n";
        echo "SKU: {$testProduct->sku}\n";
        echo "UUID: {$testProduct->uuid}\n";

        // Clean up
        $testProduct->delete();
        echo "Test product cleaned up\n";
    }
} catch (\Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
}
echo "\n";

echo "=== All Tests Completed ===\n\n";

echo "Next Steps:\n";
echo "-----------\n";
echo "1. Enable e-commerce integration for a shop (WooCommerce or Shopify)\n";
echo "2. Run: php artisan ecommerce:sync-products {shop-id} --direction=from-platform\n";
echo "3. Check synced products: php artisan tinker\n";
echo "   >>> \\App\\Models\\ProductEcommerceSync::with('product', 'shop')->get()\n\n";
