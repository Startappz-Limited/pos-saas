<?php

namespace App\Jobs;

use App\Actions\Product\SyncProductToEcommerce;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncProductsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Shop $shop,
        public string $platform,
        public string $direction,
        public ?array $options = [],
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     *
     * This job acts as a lightweight dispatcher: it fetches the product list
     * from the e-commerce platform API, then dispatches a SyncSingleProductJob
     * for each product. This keeps each job well within the 60s timeout.
     */
    public function handle(
        WooCommerceProductSyncService $wooCommerceSync,
        ShopifyProductSyncService $shopifySync,
        SyncProductToEcommerce $syncToEcommerce,
    ): void {
        try {
            // Import from platform — fetch list then dispatch per-product jobs
            if (in_array($this->direction, ['from-platform', 'both'])) {
                $this->dispatchImportJobs($wooCommerceSync, $shopifySync);
            }

            // Export to platform — dispatch per-product jobs
            if (in_array($this->direction, ['to-platform', 'both'])) {
                $this->dispatchExportJobs($syncToEcommerce);
            }
        } catch (\Exception $e) {
            Log::error('Product sync dispatcher job failed', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetch product list from API and dispatch individual sync jobs.
     */
    protected function dispatchImportJobs(
        WooCommerceProductSyncService $wooCommerceSync,
        ShopifyProductSyncService $shopifySync,
    ): void {
        Log::info('Fetching product list from platform', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
        ]);

        $fetchResult = match ($this->platform) {
            'woocommerce' => $wooCommerceSync->fetchProductsFromPlatform($this->shop, $this->options),
            'shopify' => $shopifySync->fetchProductsFromPlatform($this->shop, $this->options),
            default => ['success' => false, 'message' => "Unsupported platform: {$this->platform}"],
        };

        if (! $fetchResult['success']) {
            Log::error('Failed to fetch product list from platform', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'message' => $fetchResult['message'] ?? 'Unknown error',
            ]);

            return;
        }

        $products = $fetchResult['products'] ?? [];

        Log::info('Dispatching individual product sync jobs', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'product_count' => count($products),
        ]);

        foreach ($products as $productData) {
            SyncSingleProductJob::dispatch(
                $this->shop,
                $this->platform,
                $productData,
            );
        }

        Log::info('Product import jobs dispatched', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'dispatched' => count($products),
        ]);
    }

    /**
     * Dispatch individual export jobs for local products.
     */
    protected function dispatchExportJobs(SyncProductToEcommerce $syncToEcommerce): void
    {
        $limit = $this->options['limit'] ?? 100;
        $products = Product::active()->limit($limit)->get();

        Log::info('Starting product export to platform', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'product_count' => $products->count(),
        ]);

        $exported = 0;
        $errors = [];

        foreach ($products as $product) {
            try {
                $syncToEcommerce->execute($product, $this->shop, $this->platform);
                $exported++;
            } catch (\Exception $e) {
                $errors[] = "Product {$product->name}: " . $e->getMessage();
                Log::error('Product export error', [
                    'product_id' => $product->id,
                    'shop_id' => $this->shop->id,
                    'platform' => $this->platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Product export completed', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'exported' => $exported,
            'errors' => $errors,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Product sync dispatcher job failed after all retries', [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
            'error' => $exception->getMessage(),
        ]);
    }
}
