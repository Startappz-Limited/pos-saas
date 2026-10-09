<?php

namespace App\Jobs;

use App\Actions\Baileys\NotifyCustomerOfEcommerceOrder;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use App\Notifications\NewEcommerceOrderNotification;
use App\Services\Integration\AbandonedCartSyncService;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class ProcessOrderWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Shop $shop,
        public string $platform,
        public string $topic,
        public array $payload
    ) {
        $this->onQueue('ecommerce');
    }

    public function handle(
        WooCommerceOrderSyncService $wooCommerceService,
        ShopifyOrderSyncService $shopifyService
    ): void {
        Log::info("Processing {$this->platform} webhook", [
            'shop_id' => $this->shop->id,
            'topic' => $this->topic,
            'order_id' => $this->payload['id'] ?? 'unknown',
        ]);

        try {
            if ($this->isDeleteEvent()) {
                $this->handleDeleteEvent();

                return;
            }

            if ($this->platform === 'woocommerce') {
                $order = $wooCommerceService->upsertOrder($this->shop, $this->payload);
            } else {
                $order = $shopifyService->upsertOrder($this->shop, $this->payload);
            }

            // A recovered abandoned cart may already point at this order number.
            try {
                app(AbandonedCartSyncService::class)->linkOrder($order);
            } catch (\Throwable $e) {
                Log::warning('Linking abandoned cart to order failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notify shop manager when a brand new order arrives
            if ($order->wasRecentlyCreated) {
                $this->notifyShopUsers($order);

                // Baileys (unofficial WhatsApp) customer notification — best-effort
                try {
                    app(NotifyCustomerOfEcommerceOrder::class)->execute($order);
                } catch (\Throwable $e) {
                    Log::warning('Baileys ecommerce order notify failed', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Webhook processed successfully', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'topic' => $this->topic,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'shop_id' => $this->shop->id,
                'platform' => $this->platform,
                'topic' => $this->topic,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function isDeleteEvent(): bool
    {
        return in_array($this->topic, ['order.deleted', 'orders/delete']);
    }

    protected function handleDeleteEvent(): void
    {
        $platformOrderId = (string) ($this->payload['id'] ?? '');

        if (empty($platformOrderId)) {
            return;
        }

        $order = EcommerceOrder::where('shop_id', $this->shop->id)
            ->where('platform', $this->platform)
            ->where('platform_order_id', $platformOrderId)
            ->first();

        if ($order && ! $order->is_converted) {
            $order->delete();
            Log::info('Order soft-deleted via webhook', [
                'shop_id' => $this->shop->id,
                'platform_order_id' => $platformOrderId,
            ]);
        }
    }

    /**
     * Send email & SMS notification to the shop manager, or fall back to the shop's contact info.
     */
    protected function notifyShopUsers(EcommerceOrder $order): void
    {
        try {
            $order->loadMissing(['shop', 'items']);

            $notification = new NewEcommerceOrderNotification($order);
            $notifiedCount = 0;

            // 1. Notify shop manager if defined
            $manager = $this->shop->manager;

            if ($manager) {
                $manager->notify($notification);
                $notifiedCount++;

                Log::info('Order notification sent to shop manager', [
                    'order_id' => $order->id,
                    'user_id' => $manager->id,
                    'email' => $manager->email,
                    'phone' => $manager->phone,
                ]);
            }

            // 2. Notify other manager/super-admin users assigned to this shop
            try {
                $additionalUsers = $this->shop->users()
                    ->role(['manager', 'super-admin'])
                    ->where('users.id', '!=', $manager?->id)
                    ->get();
            } catch (RoleDoesNotExist $e) {
                $additionalUsers = collect();
            }

            foreach ($additionalUsers as $user) {
                $user->notify($notification);
                $notifiedCount++;

                Log::info('Order notification sent to shop user', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            // 3. Fallback: if nobody was notified, use the shop's contact info
            if ($notifiedCount === 0) {
                $shopEmail = $this->shop->email;
                $shopPhone = $this->shop->phone;

                if ($shopEmail || $shopPhone) {
                    $route = Notification::route('mail', $shopEmail);

                    if ($shopPhone) {
                        $route = $route->route('sms', $shopPhone)
                            ->route('whatsapp', $shopPhone);
                    }

                    Notification::send($route, $notification);

                    Log::info('Order notification sent to shop contact info (no manager)', [
                        'order_id' => $order->id,
                        'shop_id' => $this->shop->id,
                        'shop_email' => $shopEmail,
                        'shop_phone' => $shopPhone,
                    ]);
                } else {
                    Log::warning('No recipients for order notification - shop has no manager and no contact info', [
                        'order_id' => $order->id,
                        'shop_id' => $this->shop->id,
                        'shop_name' => $this->shop->name,
                    ]);
                }
            } else {
                Log::info('Order notification dispatched', [
                    'order_id' => $order->id,
                    'recipients_count' => $notifiedCount,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send new order notification', [
                'order_id' => $order->id,
                'shop_id' => $this->shop->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
