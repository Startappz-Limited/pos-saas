<?php

use App\Enums\AbandonedCartStatus;
use App\Jobs\AbandonedCartWriteBackJob;
use App\Models\AbandonedCart;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Services\Integration\AbandonedCartWriteBack;
use App\Services\Integration\EcommerceProductMatcher;
use App\Services\Integration\WooCommerceOrderSyncService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/helpers.php';

/**
 * The WooCommerce side of the integration: webhook registration, the POS →
 * plugin write-back, and the product matcher shared with order sync.
 */
beforeEach(function () {
    config(['app.url' => 'https://pos.test']);
    $this->shop = acrShop();
});

describe('webhook registration', function () {
    it('registers the order topics and the six abandoned-cart topics', function () {
        Http::fake(['shop.test/wp-json/wc/v3/webhooks' => Http::response(['id' => 1], 201)]);

        $result = app(WooCommerceOrderSyncService::class)->registerWebhooks($this->shop, 'woo-secret');

        $topics = Http::recorded()->map(fn (array $pair) => $pair[0]['topic'])->all();

        expect($result['success'])->toBeTrue()
            ->and($topics)->toBe([
                'order.created', 'order.updated', 'order.deleted',
                'acr_cart.cutoff', 'acr_cart.recovered', 'acr_email.sent',
                'acr_sms.sent', 'acr_whatsapp.sent', 'acr_link.clicked',
            ])
            ->and($result['abandoned_cart_failures'])->toBe([]);

        Http::assertSent(fn (Request $request) => $request['delivery_url'] === "https://pos.test/api/webhooks/woocommerce/{$this->shop->uuid}"
            && $request['secret'] === 'woo-secret'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('ck_test:cs_test')));
    });

    it('keeps the order webhooks when the plugin topics are rejected (plugin not installed)', function () {
        Http::fake(function (Request $request) {
            return str_starts_with((string) $request['topic'], 'acr_')
                ? Http::response(['code' => 'woocommerce_rest_webhook_invalid_topic'], 400)
                : Http::response(['id' => 1, 'topic' => $request['topic']], 201);
        });

        $result = app(WooCommerceOrderSyncService::class)->registerWebhooks($this->shop, 'woo-secret');

        expect($result['success'])->toBeTrue()
            ->and($result['webhooks'])->toHaveCount(3)
            ->and($result['abandoned_cart_webhooks'])->toBe([])
            ->and($result['abandoned_cart_failures'])->toHaveCount(6);
    });

    it('survives a connection failure on the plugin topics', function () {
        Http::fake(function (Request $request) {
            if (str_starts_with((string) $request['topic'], 'acr_')) {
                throw new ConnectionException('timeout');
            }

            return Http::response(['id' => 1], 201);
        });

        $result = app(WooCommerceOrderSyncService::class)->registerWebhooks($this->shop, 'woo-secret');

        expect($result['success'])->toBeTrue()
            ->and($result['webhooks'])->toHaveCount(3);
    });

    it('deregisters every webhook pointing at this shop, across pages, including the plugin topics', function () {
        $mine = "https://pos.test/api/webhooks/woocommerce/{$this->shop->uuid}";
        $page1 = collect(range(1, 100))->map(fn ($i) => ['id' => $i, 'topic' => 'order.created', 'delivery_url' => $i <= 3 ? $mine : 'https://elsewhere.test/hook'])->all();
        $page2 = [
            ['id' => 101, 'topic' => 'acr_cart.cutoff', 'delivery_url' => $mine],
            ['id' => 102, 'topic' => 'acr_link.clicked', 'delivery_url' => $mine],
        ];

        Http::fake(function (Request $request) use ($page1, $page2) {
            if ($request->method() !== 'GET') {
                return Http::response([], 200);
            }

            expect((int) $request['per_page'])->toBe(100);

            return Http::response((int) $request['page'] === 1 ? $page1 : $page2);
        });

        $result = app(WooCommerceOrderSyncService::class)->deregisterWebhooks($this->shop);

        $deleted = Http::recorded()
            ->filter(fn (array $pair) => $pair[0]->method() === 'DELETE')
            ->map(fn (array $pair) => (int) basename(parse_url($pair[0]->url(), PHP_URL_PATH)))
            ->values()
            ->all();

        expect($result['success'])->toBeTrue()
            ->and($deleted)->toBe([1, 2, 3, 101, 102]);
    });
});

describe('write-back to the plugin', function () {
    beforeEach(function () {
        $this->cart = AbandonedCart::factory()->for($this->shop)->create([
            'platform_cart_id' => '501',
            'status' => AbandonedCartStatus::Converted,
        ]);
    });

    it('marks the cart recovered with the invoice number as reference', function () {
        Http::fake(['shop.test/wp-json/wc-acr/v1/carts/501/status' => Http::response(['id' => 501, 'status' => 'recovered', 'unsubscribed' => false])]);

        (new AbandonedCartWriteBackJob($this->cart, 'recovered', 'INV-NRB-2026-000001', 'Sold in shop'))
            ->handle(app(AbandonedCartWriteBack::class));

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request['status'] === 'recovered'
            && $request['reference'] === 'INV-NRB-2026-000001'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('ck_test:cs_test')));

        $cart = $this->cart->fresh();

        expect($cart->platform_status)->toBe('recovered')
            ->and($cart->recovered_at)->not->toBeNull()
            ->and($cart->alerts()->where('data->event', 'write_back')->count())->toBe(1);
    });

    it('asks the website to unsubscribe the customer', function () {
        Http::fake(['shop.test/*' => Http::response(['id' => 501, 'status' => 'abandoned', 'unsubscribed' => true])]);

        (new AbandonedCartWriteBackJob($this->cart, 'unsubscribed'))->handle(app(AbandonedCartWriteBack::class));

        Http::assertSent(fn (Request $request) => $request['status'] === 'unsubscribed' && ! isset($request['reference']));
    });

    it('truncates the reference and note to what the plugin accepts', function () {
        Http::fake(['shop.test/*' => Http::response(['id' => 501, 'status' => 'recovered'])]);

        app(AbandonedCartWriteBack::class)->setStatus($this->cart, 'recovered', str_repeat('R', 300), str_repeat('N', 1500));

        Http::assertSent(fn (Request $request) => mb_strlen($request['reference']) === 191 && mb_strlen($request['note']) === 1000);
    });

    it('records a refused write-back on the timeline without throwing', function (int $status, array $body, string $expected) {
        Http::fake(['shop.test/*' => Http::response($body, $status)]);

        (new AbandonedCartWriteBackJob($this->cart, 'recovered', 'INV-1'))->handle(app(AbandonedCartWriteBack::class));

        $entry = $this->cart->alerts()->where('data->event', 'write_back_failed')->sole();

        expect($entry->message)->toContain($expected)
            ->and($this->cart->fresh()->status)->toBe(AbandonedCartStatus::Converted);
    })->with([
        'http store / read-only key' => [401, ['code' => 'acr_rest_forbidden', 'message' => 'Sorry', 'data' => ['status' => 401]], 'HTTPS'],
        'forbidden' => [403, ['code' => 'acr_rest_forbidden', 'data' => ['status' => 403]], 'Read/Write'],
        'unknown cart' => [404, ['code' => 'acr_rest_cart_not_found', 'data' => ['status' => 404]], 'no longer has this cart'],
        'closed cart' => [409, ['code' => 'acr_rest_cart_closed', 'data' => ['status' => 409, 'cart_status' => 'order_received']], 'order_received'],
        'invalid' => [422, ['code' => 'acr_rest_invalid_param', 'message' => 'reference is too long', 'data' => ['status' => 422]], 'reference is too long'],
    ]);

    it('retries an outage instead of recording it, until the last attempt', function () {
        Http::fake(['shop.test/*' => Http::response('down', 503)]);

        $job = (new AbandonedCartWriteBackJob($this->cart, 'recovered', 'INV-1'))->withFakeQueueInteractions();
        $job->handle(app(AbandonedCartWriteBack::class));

        $job->assertReleased();
        expect($this->cart->alerts()->where('data->event', 'write_back_failed')->count())->toBe(0);
    });

    it('records a failure when the integration is not configured', function () {
        $this->shop->setIntegrationConfig('woocommerce', ['enabled' => false]);
        $this->shop->save();
        Http::fake();

        (new AbandonedCartWriteBackJob($this->cart->fresh(), 'recovered', 'INV-1'))->handle(app(AbandonedCartWriteBack::class));

        Http::assertNothingSent();
        expect($this->cart->alerts()->where('data->event', 'write_back_failed')->count())->toBe(1);
    });
});

describe('the shared product matcher', function () {
    it('keeps order sync matching exactly as before: sync record, then SKU', function () {
        $synced = Product::factory()->create(['sku' => 'SYNCED']);
        ProductEcommerceSync::create(['product_id' => $synced->id, 'shop_id' => $this->shop->id, 'platform' => 'woocommerce', 'platform_product_id' => '55']);
        $bySku = Product::factory()->create(['sku' => 'BY-SKU']);

        $order = app(WooCommerceOrderSyncService::class)->upsertOrder($this->shop, [
            'id' => 3001,
            'status' => 'processing',
            'total' => '100',
            'line_items' => [
                ['id' => 1, 'product_id' => 55, 'sku' => 'IGNORED', 'name' => 'A', 'quantity' => 1, 'price' => 10],
                ['id' => 2, 'product_id' => 56, 'sku' => 'BY-SKU', 'name' => 'B', 'quantity' => 1, 'price' => 10],
                ['id' => 3, 'product_id' => 57, 'sku' => '', 'name' => 'C', 'quantity' => 1, 'price' => 10],
            ],
        ]);

        expect(EcommerceOrder::find($order->id)->items()->orderBy('platform_line_item_id')->pluck('product_id')->all())
            ->toBe([$synced->id, $bySku->id, null]);
    });

    it('scopes the sync record to the shop and platform', function () {
        $product = Product::factory()->create();
        ProductEcommerceSync::create(['product_id' => $product->id, 'shop_id' => $this->shop->id, 'platform' => 'shopify', 'platform_product_id' => '55']);

        expect(app(EcommerceProductMatcher::class)->matchProductId($this->shop, 'woocommerce', '55', null))->toBeNull()
            ->and(app(EcommerceProductMatcher::class)->matchProductId($this->shop, 'shopify', '55', null))->toBe($product->id);
    });
});
