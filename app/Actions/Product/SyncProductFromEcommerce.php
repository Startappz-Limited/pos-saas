<?php

namespace App\Actions\Product;

use App\Models\Shop;
use App\Services\Integration\ShopifyProductSyncService;
use App\Services\Integration\WooCommerceProductSyncService;
use Illuminate\Support\Facades\Log;

class SyncProductFromEcommerce
{
    public function __construct(
        protected WooCommerceProductSyncService $wooCommerceSync,
        protected ShopifyProductSyncService $shopifySync
    ) {}

    /**
     * Sync products from an e-commerce platform to local database
     */
    public function execute(Shop $shop, string $platform, ?array $options = []): array
    {
        try {
            $result = match ($platform) {
                'woocommerce' => $this->wooCommerceSync->syncFromPlatform($shop, $options),
                'shopify' => $this->shopifySync->syncFromPlatform($shop, $options),
                default => ['success' => false, 'message' => "Unsupported platform: {$platform}"],
            };

            if ($result['success']) {
                Log::info('Products synced from e-commerce platform', [
                    'shop_id' => $shop->id,
                    'platform' => $platform,
                    'synced' => $result['synced'] ?? 0,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to sync products from e-commerce', [
                'shop_id' => $shop->id,
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync from all integrated platforms for a shop
     */
    public function executeFromAllPlatforms(Shop $shop, ?array $options = []): array
    {
        $results = [];
        $enabledIntegrations = $shop->getEnabledIntegrations();

        foreach ($enabledIntegrations as $platform) {
            $results[$platform] = $this->execute($shop, $platform, $options[$platform] ?? []);
        }

        return $results;
    }
}
