<?php

use App\Models\Product;
use App\Models\Shop;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/*
 * An inactive product reaches its online store as a draft, not published (stage 11, step 6: the
 * out-of-stock status is inactive now, and an inactive product must stay off the store). Jobs are
 * faked, so creating a product doesn't push it to a store during the test.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    Bus::fake();
    $this->shop = Shop::factory()->create([
        'settings' => ['integrations' => ['woocommerce' => [
            'enabled' => true,
            'store_url' => 'https://store.example.test',
            'consumer_key' => Crypt::encrypt('ck_test'),
            'consumer_secret' => Crypt::encrypt('cs_test'),
        ]]],
    ]);
    Http::fake(['*' => Http::response(['id' => 77, 'status' => 'draft'], 201)]);
});

it('sends an inactive product to the store as a draft', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'status' => 'inactive']);

    app(WooCommerceProductSyncService::class)->syncToPlatform($this->shop, $product);

    Http::assertSent(fn ($request) => ($request->data()['status'] ?? null) === 'draft');
});

it('publishes an active product', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'status' => 'active']);

    app(WooCommerceProductSyncService::class)->syncToPlatform($this->shop, $product);

    Http::assertSent(fn ($request) => ($request->data()['status'] ?? null) === 'publish');
});
