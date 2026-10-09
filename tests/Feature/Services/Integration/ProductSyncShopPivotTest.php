<?php

use App\Models\Product;
use App\Models\Shop;
use App\Services\Integration\ImageDownloadService;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->imageDownloadService = Mockery::mock(ImageDownloadService::class);
    $this->imageDownloadService->shouldReceive('extractImageUrls')->andReturn([]);
    $this->imageDownloadService->shouldReceive('downloadImages')->andReturn([]);

    $this->shop = Shop::factory()->create([
        'settings' => [
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
            'integrations' => [
                'woocommerce' => [
                    'enabled' => true,
                    'store_url' => 'https://test-store.com',
                    'consumer_key' => Crypt::encrypt('ck_test'),
                    'consumer_secret' => Crypt::encrypt('cs_test'),
                ],
                'shopify' => [
                    'enabled' => true,
                    'shop_domain' => 'test-store.myshopify.com',
                    'access_token' => Crypt::encrypt('shpat_test'),
                ],
            ],
        ],
    ]);
});

test('woocommerce sync attaches product to shop in pivot table', function () {
    Http::fake([
        '*/wp-json/wc/v3/products*' => Http::response([
            [
                'id' => 101,
                'name' => 'WC Test Product',
                'sku' => 'WC-PIVOT-001',
                'description' => 'Test description',
                'regular_price' => '25.00',
                'price' => '20.00',
                'stock_quantity' => 15,
                'manage_stock' => true,
                'status' => 'publish',
                'categories' => [],
                'images' => [],
            ],
        ]),
    ]);

    $service = new WooCommerceProductSyncService($this->imageDownloadService);
    $result = $service->syncFromPlatform($this->shop);

    expect($result['success'])->toBeTrue()
        ->and($result['created'])->toBe(1);

    $product = Product::where('sku', 'WC-PIVOT-001')->first();
    expect($product)->not->toBeNull();

    // Verify the product is attached to the shop via pivot table
    $pivotRecord = $product->shops()->where('shops.id', $this->shop->id)->first();
    expect($pivotRecord)->not->toBeNull()
        ->and($pivotRecord->pivot->stock_quantity)->toBe(15)
        ->and((float) $pivotRecord->pivot->selling_price)->toBe(20.00)
        ->and((bool) $pivotRecord->pivot->is_active)->toBeTrue();

    // `regular_price` (25.00) is the pre-discount SELLING price, not a cost.
    // Writing it here made the product sell at a loss on paper, so with no
    // cost-of-goods meta on the payload the cost must stay unknown.
    expect($pivotRecord->pivot->cost_price)->toBeNull()
        ->and($product->cost_price)->toBeNull();
});

test('shopify sync attaches product to shop in pivot table', function () {
    Http::fake([
        '*/admin/api/2024-01/products.json*' => Http::response([
            'products' => [
                [
                    'id' => 201,
                    'title' => 'Shopify Test Product',
                    'body_html' => '<p>Test</p>',
                    'status' => 'active',
                    'handle' => 'shopify-test',
                    'images' => [],
                    'variants' => [
                        [
                            'id' => 301,
                            'title' => 'Default Title',
                            'sku' => 'SH-PIVOT-001',
                            'price' => '30.00',
                            'compare_at_price' => '35.00',
                            'inventory_quantity' => 25,
                            'inventory_management' => 'shopify',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new ShopifyProductSyncService($this->imageDownloadService);
    $result = $service->syncFromPlatform($this->shop);

    expect($result['success'])->toBeTrue()
        ->and($result['created'])->toBe(1);

    $product = Product::where('sku', 'SH-PIVOT-001')->first();
    expect($product)->not->toBeNull();

    // Verify the product is attached to the shop via pivot table
    $pivotRecord = $product->shops()->where('shops.id', $this->shop->id)->first();
    expect($pivotRecord)->not->toBeNull()
        ->and($pivotRecord->pivot->stock_quantity)->toBe(25)
        ->and((float) $pivotRecord->pivot->selling_price)->toBe(30.00)
        ->and((bool) $pivotRecord->pivot->is_active)->toBeTrue();

    // `compare_at_price` (35.00) is the struck-through "was" price and is always
    // at or above what the customer pays. Shopify's real cost lives on the
    // InventoryItem, which this payload does not carry.
    expect($pivotRecord->pivot->cost_price)->toBeNull()
        ->and($product->cost_price)->toBeNull();
});

test('woocommerce sync updates pivot data on re-sync', function () {
    $product = Product::factory()->create(['sku' => 'WC-RESYNC-001']);
    $product->shops()->attach($this->shop->id, [
        'stock_quantity' => 5,
        'selling_price' => 10.00,
        'cost_price' => 8.00,
        'is_active' => true,
    ]);

    Http::fake([
        '*/wp-json/wc/v3/products*' => Http::response([
            [
                'id' => 102,
                'name' => $product->name,
                'sku' => 'WC-RESYNC-001',
                'description' => 'Updated',
                'regular_price' => '50.00',
                'price' => '40.00',
                'stock_quantity' => 99,
                'manage_stock' => true,
                'status' => 'publish',
                'categories' => [],
                'images' => [],
            ],
        ]),
    ]);

    $service = new WooCommerceProductSyncService($this->imageDownloadService);
    $result = $service->syncFromPlatform($this->shop);

    expect($result['success'])->toBeTrue();

    $pivotRecord = $product->shops()->where('shops.id', $this->shop->id)->first();
    expect($pivotRecord)->not->toBeNull()
        ->and($pivotRecord->pivot->stock_quantity)->toBe(99)
        ->and((float) $pivotRecord->pivot->selling_price)->toBe(40.00);

    // A re-sync refreshes stock and price but must leave the shop's own
    // purchase cost (8.00) alone — WooCommerce has nothing more authoritative
    // to replace it with, and overwriting it is what corrupted the live data.
    expect((float) $pivotRecord->pivot->cost_price)->toBe(8.00);
});
