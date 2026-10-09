<?php

namespace App\Observers;

use App\Actions\Product\SyncProductToEcommerce;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Flag to prevent sync loops when product is being updated from sync
     */
    public static bool $syncInProgress = false;

    public function __construct(
        protected SyncProductToEcommerce $syncToEcommerce
    ) {}

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        $this->autoSyncProduct($product, 'created');
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        $this->autoSyncProduct($product, 'updated');
    }

    /**
     * Automatically sync product to all linked e-commerce shops
     */
    protected function autoSyncProduct(Product $product, string $action): void
    {
        // Prevent infinite loops - don't sync if we're already syncing
        if (self::$syncInProgress) {
            return;
        }

        try {
            // Get all shops with e-commerce integrations enabled
            $shopsWithIntegrations = Shop::whereRaw(
                "JSON_EXTRACT(settings, '$.woocommerce.enabled') = true OR JSON_EXTRACT(settings, '$.shopify.enabled') = true"
            )->get();

            if ($shopsWithIntegrations->isEmpty()) {
                return;
            }

            Log::info('Auto-syncing product to e-commerce platforms', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'action' => $action,
                'shops_count' => $shopsWithIntegrations->count(),
            ]);

            // Sync to each shop's enabled platforms
            foreach ($shopsWithIntegrations as $shop) {
                // Check WooCommerce
                if ($shop->isIntegrationEnabled('woocommerce')) {
                    try {
                        $this->syncToEcommerce->execute($product, $shop, 'woocommerce');
                        Log::info('Product auto-synced to WooCommerce', [
                            'product_id' => $product->id,
                            'shop_id' => $shop->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to auto-sync product to WooCommerce', [
                            'product_id' => $product->id,
                            'shop_id' => $shop->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Check Shopify
                if ($shop->isIntegrationEnabled('shopify')) {
                    try {
                        $this->syncToEcommerce->execute($product, $shop, 'shopify');
                        Log::info('Product auto-synced to Shopify', [
                            'product_id' => $product->id,
                            'shop_id' => $shop->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to auto-sync product to Shopify', [
                            'product_id' => $product->id,
                            'shop_id' => $shop->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Auto-sync failed', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }
}
