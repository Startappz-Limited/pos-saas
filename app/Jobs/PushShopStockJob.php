<?php

namespace App\Jobs;

use App\Actions\Product\SyncProductToEcommerce;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Sends one shop's stock of a product to that shop's online stores (stage
 * 1c): queued by the stock ledger whenever the shop's stock changes, so each
 * store shows its own shop's figure and no other shop's change reaches it.
 *
 * Unique per shop and product while waiting, so a sale of many units, or a
 * burst of sales, sends the latest figure once.
 */
class PushShopStockJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $shopId,
        public int $productId,
    ) {
        $this->onQueue('ecommerce');
    }

    public function uniqueId(): string
    {
        return "{$this->shopId}:{$this->productId}";
    }

    public function handle(SyncProductToEcommerce $sync): void
    {
        $shop = Shop::find($this->shopId);
        $product = Product::find($this->productId);

        if ($shop === null || $product === null || ! $product->track_stock) {
            return;
        }

        foreach (['woocommerce', 'shopify'] as $platform) {
            if (! $shop->isIntegrationEnabled($platform) || $product->getSyncRecord($shop->id, $platform) === null) {
                continue;
            }

            $result = $sync->syncInventory($product, $shop, $platform);

            if (! ($result['success'] ?? false)) {
                Log::warning('Shop stock push failed', [
                    'shop_id' => $shop->id,
                    'product_id' => $product->id,
                    'platform' => $platform,
                    'message' => $result['message'] ?? null,
                ]);
            }
        }
    }
}
