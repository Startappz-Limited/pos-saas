<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncSingleProductJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Shop $shop,
        public string $platform,
        public array $productData,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WooCommerceProductSyncService $wooCommerceSync,
        ShopifyProductSyncService $shopifySync,
    ): void {
        $productId = $this->productData['id'] ?? 'unknown';

        try {
            $results = match ($this->platform) {
                'woocommerce' => $this->syncWooCommerceProduct($wooCommerceSync),
                'shopify' => $this->syncShopifyProduct($shopifySync),
                default => throw new \InvalidArgumentException("Unsupported platform: {$this->platform}"),
            };

            Log::info('Single product sync completed', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'platform_product_id' => $productId,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Single product sync failed', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'platform_product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single WooCommerce product.
     */
    protected function syncWooCommerceProduct(WooCommerceProductSyncService $service): array
    {
        return $service->syncSingleProduct($this->shop, $this->productData);
    }

    /**
     * Sync a single Shopify product (handles multiple variants).
     */
    protected function syncShopifyProduct(ShopifyProductSyncService $service): array
    {
        $results = [];

        foreach ($this->productData['variants'] ?? [] as $variant) {
            $results[] = $service->syncSingleVariant($this->shop, $this->productData, $variant);
        }

        return $results;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Single product sync failed after all retries', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'platform_product_id' => $this->productData['id'] ?? 'unknown',
            'error' => $exception->getMessage(),
        ]);
    }
}
