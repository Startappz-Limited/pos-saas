<?php

namespace App\Actions\Product;

use App\Models\Product;
use App\Models\Shop;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Support\Facades\Log;

class SyncProductToEcommerce
{
    public function __construct(
        protected WooCommerceProductSyncService $wooCommerceSync,
        protected ShopifyProductSyncService $shopifySync
    ) {}

    /**
     * Sync a product to an e-commerce platform
     */
    public function execute(Product $product, Shop $shop, string $platform, ?array $options = []): array
    {
        try {
            $result = match ($platform) {
                'woocommerce' => $this->wooCommerceSync->syncToPlatform($shop, $product, $options),
                'shopify' => $this->shopifySync->syncToPlatform($shop, $product, $options),
                default => ['success' => false, 'message' => "Unsupported platform: {$platform}"],
            };

            if ($result['success']) {
                Log::info('Product synced to e-commerce platform', [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'platform' => $platform,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to sync product to e-commerce', [
                'product_id' => $product->id,
                'shop_id' => $shop->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync a product to all integrated platforms for a shop
     */
    public function executeToAllPlatforms(Product $product, Shop $shop, ?array $options = []): array
    {
        $results = [];
        $enabledIntegrations = $shop->getEnabledIntegrations();

        foreach ($enabledIntegrations as $platform) {
            $results[$platform] = $this->execute($product, $shop, $platform, $options[$platform] ?? []);
        }

        return $results;
    }

    /**
     * Sync inventory only
     */
    public function syncInventory(Product $product, Shop $shop, string $platform): array
    {
        try {
            $result = match ($platform) {
                'woocommerce' => $this->wooCommerceSync->syncInventory($shop, $product),
                'shopify' => $this->shopifySync->syncInventory($shop, $product),
                default => ['success' => false, 'message' => "Unsupported platform: {$platform}"],
            };

            return $result;
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
