<?php

namespace App\Jobs;

use App\Models\EcommerceOrder;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncOrderStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public EcommerceOrder $order,
        public string $newStatus,
        public ?string $note = null
    ) {
        $this->onQueue('ecommerce');
    }

    public function handle(
        WooCommerceOrderSyncService $wooCommerceService,
        ShopifyOrderSyncService $shopifyService
    ): void {
        $shop = $this->order->shop;

        Log::info("Syncing order status to {$this->order->platform}", [
            'order_id' => $this->order->id,
            'platform_order_id' => $this->order->platform_order_id,
            'new_status' => $this->newStatus,
        ]);

        try {
            $result = match ($this->order->platform) {
                'woocommerce' => $wooCommerceService->updateOrderStatus(
                    $shop,
                    $this->order->platform_order_id,
                    $wooCommerceService->mapToWooCommerceStatus($this->newStatus),
                    $this->note
                ),
                'shopify' => $shopifyService->updateOrderStatus(
                    $shop,
                    $this->order->platform_order_id,
                    $this->newStatus
                ),
                default => ['success' => false, 'message' => "Unknown platform: {$this->order->platform}"],
            };

            if ($result['success']) {
                $this->order->updatePlatformStatus($this->newStatus);

                Log::info('Order status synced successfully', [
                    'order_id' => $this->order->id,
                    'new_status' => $this->newStatus,
                ]);
            } else {
                Log::error('Order status sync failed', [
                    'order_id' => $this->order->id,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SyncOrderStatusJob exception', [
                'order_id' => $this->order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
