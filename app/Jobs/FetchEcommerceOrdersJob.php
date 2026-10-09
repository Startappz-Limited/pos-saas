<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchEcommerceOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public Shop $shop,
        public string $platform,
        public array $options = []
    ) {
        $this->onQueue('ecommerce');
    }

    public function handle(
        WooCommerceOrderSyncService $wooCommerceService,
        ShopifyOrderSyncService $shopifyService
    ): void {
        Log::info("Fetching {$this->platform} orders for shop", [
            'shop_id' => $this->shop->id,
            'platform' => $this->platform,
        ]);

        try {
            $result = match ($this->platform) {
                'woocommerce' => $wooCommerceService->syncOrders($this->shop, $this->options),
                'shopify' => $shopifyService->syncOrders($this->shop, $this->options),
                default => ['success' => false, 'synced' => 0, 'message' => "Unknown platform: {$this->platform}"],
            };

            if ($result['success']) {
                Log::info('Orders fetched successfully', [
                    'shop_id' => $this->shop->id,
                    'platform' => $this->platform,
                    'synced' => $result['synced'],
                ]);
            } else {
                Log::error('Orders fetch failed', [
                    'shop_id' => $this->shop->id,
                    'platform' => $this->platform,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('FetchEcommerceOrdersJob exception', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
