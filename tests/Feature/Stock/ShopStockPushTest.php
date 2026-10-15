<?php

use App\Enums\StockMovementType;
use App\Jobs\PushShopStockJob;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\Shop;
use App\Observers\ProductObserver;
use App\Services\Stock\StockLedger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * A stock change in one shop updates that shop's online store and no other
 * (stage 1c acceptance): the ledger queues a push for the shop whose stock
 * changed, and the push sends that shop's own figure.
 */
function shopWithStore(string $code, string $url): Shop
{
    return Shop::factory()->create(['code' => $code, 'settings' => ['integrations' => ['woocommerce' => [
        'enabled' => true,
        'store_url' => $url,
        'consumer_key' => Crypt::encrypt('ck_'.$code),
        'consumer_secret' => Crypt::encrypt('cs_'.$code),
    ]]]]);
}

beforeEach(function () {
    $this->ledger = app(StockLedger::class);
    $this->nairobi = shopWithStore('NRB', 'https://nairobi.example');
    $this->mombasa = shopWithStore('MSA', 'https://mombasa.example');
    $this->product = Product::factory()->create(['shop_id' => $this->nairobi->id, 'stock_quantity' => 0, 'track_stock' => true]);

    foreach ([$this->nairobi, $this->mombasa] as $shop) {
        ProductEcommerceSync::create([
            'uuid' => (string) Str::uuid(), 'product_id' => $this->product->id, 'shop_id' => $shop->id,
            'platform' => 'woocommerce', 'platform_product_id' => "wc-{$shop->code}",
            'sync_status' => 'synced', 'sync_direction' => 'to_platform',
        ]);
    }
});

it('queues a stock push for the shop whose stock changed, and no other', function () {
    Queue::fake();

    $this->ledger->receive($this->nairobi->id, $this->product->defaultVariation, 7, 10, StockMovementType::INTAKE);

    Queue::assertPushed(PushShopStockJob::class, 1);
    Queue::assertPushed(PushShopStockJob::class, fn (PushShopStockJob $job) => $job->shopId === $this->nairobi->id && $job->productId === $this->product->id);
});

it('sends the shop\'s own figure to the shop\'s own store', function () {
    Queue::fake();
    $this->ledger->receive($this->nairobi->id, $this->product->defaultVariation, 7, 10, StockMovementType::INTAKE);
    $this->ledger->receive($this->mombasa->id, $this->product->defaultVariation, 3, 10, StockMovementType::INTAKE);
    Http::fake(['*' => Http::response(['id' => 1])]);

    app()->call([new PushShopStockJob($this->nairobi->id, $this->product->id), 'handle']);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://nairobi.example/wp-json/wc/v3/products/wc-NRB'
        && $request['stock_quantity'] === 7);
});

it('pushes nothing for a shop without a store, or while a store\'s figures are being written in', function () {
    Queue::fake();
    $plain = Shop::factory()->create();

    $this->ledger->receive($plain->id, $this->product->defaultVariation, 2, 10, StockMovementType::INTAKE);

    ProductObserver::$syncInProgress = true;
    try {
        $this->ledger->setLevel($this->nairobi->id, $this->product->defaultVariation, 9);
    } finally {
        ProductObserver::$syncInProgress = false;
    }

    Queue::assertNotPushed(PushShopStockJob::class);
});
