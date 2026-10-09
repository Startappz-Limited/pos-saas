<?php

namespace App\Services\Integration;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\Shop;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WooCommerceOrderSyncService
{
    /**
     * Fetch orders from WooCommerce API.
     *
     * @return array{success: bool, orders?: array, message?: string}
     */
    public function fetchOrders(Shop $shop, array $options = []): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $params = [
                'per_page' => $options['per_page'] ?? 100,
                'page' => $options['page'] ?? 1,
                'orderby' => 'date',
                'order' => 'desc',
            ];

            if (! empty($options['status'])) {
                $params['status'] = $options['status'];
            }

            if (! empty($options['after'])) {
                $params['after'] = $options['after'];
            }

            if (! empty($options['before'])) {
                $params['before'] = $options['before'];
            }

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get("{$storeUrl}/wp-json/wc/v3/orders", $params);

            if (! $response->successful()) {
                Log::error('WooCommerce Orders API request failed', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ]);

                return ['success' => false, 'message' => 'API request failed: '.$response->status()];
            }

            return ['success' => true, 'orders' => $response->json()];
        } catch (\Exception $e) {
            Log::error('WooCommerce Orders fetch exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch a single order from WooCommerce.
     *
     * @return array{success: bool, order?: array, message?: string}
     */
    public function fetchSingleOrder(Shop $shop, string $orderId): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get("{$storeUrl}/wp-json/wc/v3/orders/{$orderId}");

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'API request failed: '.$response->status()];
            }

            return ['success' => true, 'order' => $response->json()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update an order's status on WooCommerce.
     *
     * @return array{success: bool, message?: string}
     */
    public function updateOrderStatus(Shop $shop, string $orderId, string $status, ?string $note = null): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $body = ['status' => $status];

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->put("{$storeUrl}/wp-json/wc/v3/orders/{$orderId}", $body);

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Status update failed: '.$response->status()];
            }

            if ($note) {
                $this->addOrderNote($shop, $orderId, $note);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('WooCommerce order status update failed', [
                'shop_id' => $shop->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync edited order items to WooCommerce before converting to a local sale.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{success: bool, message?: string}
     */
    public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['success' => false, 'message' => 'WooCommerce API credentials are incomplete.'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $payload = [
                'line_items' => $this->buildWooCommerceLineItems($shop, $order, $items),
            ];

            if ($shippingTotal !== null) {
                $payload['shipping_lines'] = $this->buildWooCommerceShippingLines($order, $shippingTotal);
            }

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->put("{$storeUrl}/wp-json/wc/v3/orders/{$order->platform_order_id}", $payload);

            if (! $response->successful()) {
                Log::error('WooCommerce order item sync failed', [
                    'shop_id' => $shop->id,
                    'order_id' => $order->id,
                    'status_code' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ]);

                return ['success' => false, 'message' => 'Order item update failed: '.$response->status()];
            }

            $this->upsertOrder($shop, $response->json());
            $this->addOrderNote($shop, $order->platform_order_id, 'Order items adjusted during POS conversion.');

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('WooCommerce order item sync exception', [
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Add a note to a WooCommerce order.
     */
    public function addOrderNote(Shop $shop, string $orderId, string $note): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->post("{$storeUrl}/wp-json/wc/v3/orders/{$orderId}/notes", [
                    'note' => $note,
                    'customer_note' => false,
                ]);

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    protected function buildWooCommerceLineItems(Shop $shop, EcommerceOrder $order, array $items): array
    {
        $order->loadMissing('items');
        $submittedByOrderItemId = collect($items)
            ->filter(fn (array $item): bool => ! empty($item['order_item_id']))
            ->keyBy(fn (array $item): int => (int) $item['order_item_id']);

        $lineItems = [];

        foreach ($order->items as $orderItem) {
            $submittedItem = $submittedByOrderItemId->get($orderItem->id);

            if (! $submittedItem) {
                if ($orderItem->platform_line_item_id) {
                    $lineItems[] = [
                        'id' => (int) $orderItem->platform_line_item_id,
                        'quantity' => 0,
                    ];
                }

                continue;
            }

            $sameProduct = (int) ($submittedItem['product_id'] ?? 0) === (int) ($orderItem->product_id ?? 0);

            if ($sameProduct && $orderItem->platform_line_item_id) {
                $lineItems[] = $this->makeWooCommerceLineItem($shop, $submittedItem, $orderItem);

                continue;
            }

            if ($orderItem->platform_line_item_id) {
                $lineItems[] = [
                    'id' => (int) $orderItem->platform_line_item_id,
                    'quantity' => 0,
                ];
            }

            $lineItems[] = $this->makeWooCommerceLineItem($shop, $submittedItem);
        }

        foreach ($items as $item) {
            if (! empty($item['order_item_id'])) {
                continue;
            }

            $lineItems[] = $this->makeWooCommerceLineItem($shop, $item);
        }

        return $lineItems;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected function makeWooCommerceLineItem(Shop $shop, array $item, ?EcommerceOrderItem $orderItem = null): array
    {
        $quantity = (int) $item['quantity'];
        $unitPrice = (float) $item['unit_price'];
        $lineTotal = number_format($quantity * $unitPrice, 2, '.', '');

        $lineItem = [
            'quantity' => $quantity,
            'subtotal' => $lineTotal,
            'total' => $lineTotal,
        ];

        if ($orderItem?->platform_line_item_id) {
            $lineItem['id'] = (int) $orderItem->platform_line_item_id;
        }

        $platformProductId = $this->resolveWooCommerceProductId($shop, (int) $item['product_id'])
            ?? $orderItem?->platform_product_id;

        if (! $platformProductId) {
            $productName = Product::find($item['product_id'])?->name ?? 'Selected product';

            throw new \RuntimeException("{$productName} is not synced with WooCommerce and cannot be added to the website order.");
        }

        $lineItem['product_id'] = (int) $platformProductId;

        return $lineItem;
    }

    protected function resolveWooCommerceProductId(Shop $shop, int $productId): ?string
    {
        return ProductEcommerceSync::where('shop_id', $shop->id)
            ->where('platform', 'woocommerce')
            ->where('product_id', $productId)
            ->value('platform_product_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function buildWooCommerceShippingLines(EcommerceOrder $order, float $shippingTotal): array
    {
        $existingShippingLines = $order->platform_data['shipping_lines'] ?? [];
        $formattedTotal = number_format($shippingTotal, 2, '.', '');

        if ($shippingTotal > 0) {
            $shippingLine = [
                'method_title' => $existingShippingLines[0]['method_title'] ?? 'Delivery',
                'method_id' => $existingShippingLines[0]['method_id'] ?? 'flat_rate',
                'total' => $formattedTotal,
            ];

            if (! empty($existingShippingLines[0]['id'])) {
                $shippingLine['id'] = (int) $existingShippingLines[0]['id'];
            }

            return [$shippingLine];
        }

        return collect($existingShippingLines)
            ->filter(fn (array $line): bool => ! empty($line['id']))
            ->map(fn (array $line): array => [
                'id' => (int) $line['id'],
                'total' => '0.00',
            ])
            ->values()
            ->all();
    }

    /**
     * Sync orders from WooCommerce into local ecommerce_orders table.
     *
     * @return array{success: bool, synced: int, message?: string}
     */
    public function syncOrders(Shop $shop, array $options = []): array
    {
        $result = $this->fetchOrders($shop, $options);

        if (! $result['success']) {
            return ['success' => false, 'synced' => 0, 'message' => $result['message']];
        }

        $synced = 0;
        foreach ($result['orders'] as $apiOrder) {
            $this->upsertOrder($shop, $apiOrder);
            $synced++;
        }

        return ['success' => true, 'synced' => $synced];
    }

    /**
     * Upsert a single WooCommerce order into the local database.
     */
    public function upsertOrder(Shop $shop, array $apiOrder): EcommerceOrder
    {
        $mapped = $this->mapToLocal($apiOrder);

        $order = EcommerceOrder::withTrashed()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'platform' => 'woocommerce',
                'platform_order_id' => (string) $apiOrder['id'],
            ],
            array_merge($mapped, [
                'shop_id' => $shop->id,
                'last_synced_at' => now(),
            ])
        );

        // Restore if previously soft-deleted
        if ($order->trashed()) {
            $order->restore();
        }

        // Sync line items
        $this->syncLineItems($order, $apiOrder['line_items'] ?? [], $shop);

        return $order;
    }

    /**
     * Map WooCommerce API order to local columns.
     *
     * @return array<string, mixed>
     */
    public function mapToLocal(array $apiOrder): array
    {
        $billing = $apiOrder['billing'] ?? [];
        $shipping = $apiOrder['shipping'] ?? [];
        $customerName = trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? ''));

        return [
            'platform' => 'woocommerce',
            'platform_order_id' => (string) $apiOrder['id'],
            'order_number' => '#'.($apiOrder['number'] ?? $apiOrder['id']),
            'status' => $this->mapStatus($apiOrder['status'] ?? 'pending'),
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'currency' => $apiOrder['currency'] ?? 'KES',
            'subtotal' => floatval($apiOrder['total'] ?? 0) - floatval($apiOrder['shipping_total'] ?? 0) - floatval($apiOrder['total_tax'] ?? 0) + floatval($apiOrder['discount_total'] ?? 0),
            'discount_total' => floatval($apiOrder['discount_total'] ?? 0),
            'shipping_total' => floatval($apiOrder['shipping_total'] ?? 0),
            'tax_total' => floatval($apiOrder['total_tax'] ?? 0),
            'total' => floatval($apiOrder['total'] ?? 0),
            'customer_name' => $customerName ?: null,
            'customer_email' => $billing['email'] ?? null,
            'customer_phone' => $billing['phone'] ?? null,
            'billing_address' => ! empty($billing) ? $billing : null,
            'shipping_address' => ! empty($shipping) ? $shipping : null,
            'notes' => $apiOrder['customer_note'] ?? null,
            'platform_created_at' => $apiOrder['date_created'] ?? null,
            'platform_updated_at' => $apiOrder['date_modified'] ?? null,
            'platform_data' => $apiOrder,
        ];
    }

    /**
     * Map WooCommerce order status to local EcommerceOrderStatus.
     */
    protected function mapStatus(string $wcStatus): string
    {
        return match ($wcStatus) {
            'pending' => 'pending',
            'processing' => 'processing',
            'on-hold' => 'on-hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }

    /**
     * Determine payment status from WooCommerce order data.
     */
    protected function mapPaymentStatus(string $wcStatus, ?string $datePaid): string
    {
        if (in_array($wcStatus, ['refunded'])) {
            return 'refunded';
        }

        if ($datePaid || in_array($wcStatus, ['processing', 'completed'])) {
            return 'paid';
        }

        return 'unpaid';
    }

    /**
     * Sync line items for an order, matching to local products.
     */
    protected function syncLineItems(EcommerceOrder $order, array $lineItems, Shop $shop): void
    {
        $existingItemIds = $order->items()->pluck('id')->toArray();
        $processedIds = [];

        foreach ($lineItems as $item) {
            $productId = $this->matchLocalProduct($shop, (string) ($item['product_id'] ?? ''), $item['sku'] ?? null);

            $orderItem = EcommerceOrderItem::updateOrCreate(
                [
                    'ecommerce_order_id' => $order->id,
                    'platform_line_item_id' => (string) $item['id'],
                ],
                [
                    'platform_product_id' => (string) ($item['product_id'] ?? ''),
                    'product_id' => $productId,
                    'name' => $item['name'] ?? 'Unknown Product',
                    'sku' => $item['sku'] ?? null,
                    'quantity' => intval($item['quantity'] ?? 1),
                    'unit_price' => floatval($item['price'] ?? 0),
                    'subtotal' => floatval($item['subtotal'] ?? 0),
                    'total' => floatval($item['total'] ?? 0),
                    'tax_total' => floatval($item['total_tax'] ?? 0),
                    'platform_data' => $item,
                ]
            );

            $processedIds[] = $orderItem->id;
        }

        // Remove items no longer in the API response
        $removedIds = array_diff($existingItemIds, $processedIds);
        if (! empty($removedIds)) {
            EcommerceOrderItem::whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * Match a platform product to a local product via ProductEcommerceSync or SKU.
     *
     * Delegates to the shared matcher so abandoned-cart sync resolves products
     * exactly the way order sync does.
     */
    protected function matchLocalProduct(Shop $shop, string $platformProductId, ?string $sku): ?int
    {
        return app(EcommerceProductMatcher::class)->matchProductId($shop, 'woocommerce', $platformProductId, $sku);
    }

    /**
     * Topics from the Abandoned Cart Recovery plugin that the POS subscribes to.
     * They only exist when that plugin is installed on the store.
     *
     * @var array<int, string>
     */
    public const ABANDONED_CART_TOPICS = [
        'acr_cart.cutoff',
        'acr_cart.recovered',
        'acr_email.sent',
        'acr_sms.sent',
        'acr_whatsapp.sent',
        'acr_link.clicked',
    ];

    /**
     * Register webhooks on WooCommerce for order events, plus the abandoned-cart
     * topics when the store runs the Abandoned Cart Recovery plugin.
     *
     * The abandoned-cart topics are optional: a store without the plugin rejects
     * them, and that is logged as a warning without affecting the order webhooks
     * or the result's `success`.
     *
     * @return array{success: bool, webhooks?: array, abandoned_cart_webhooks?: array, abandoned_cart_failures?: array, message?: string}
     */
    public function registerWebhooks(Shop $shop, string $secret): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);
            $baseUrl = rtrim(config('app.url'), '/');
            $deliveryUrl = "{$baseUrl}/api/webhooks/woocommerce/{$shop->uuid}";

            $topics = ['order.created', 'order.updated', 'order.deleted'];
            $registered = [];

            foreach ($topics as $topic) {
                $response = Http::timeout(30)
                    ->withBasicAuth($consumerKey, $consumerSecret)
                    ->post("{$storeUrl}/wp-json/wc/v3/webhooks", [
                        'name' => "Fitness Center - {$topic}",
                        'topic' => $topic,
                        'delivery_url' => $deliveryUrl,
                        'secret' => $secret,
                        'status' => 'active',
                    ]);

                if ($response->successful()) {
                    $registered[] = $response->json();
                } else {
                    Log::warning('WooCommerce webhook registration failed', [
                        'shop_id' => $shop->id,
                        'topic' => $topic,
                        'status' => $response->status(),
                        'error' => $response->json(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('WooCommerce webhook registration exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }

        [$cartWebhooks, $cartFailures] = $this->registerAbandonedCartWebhooks(
            $shop, $storeUrl, $consumerKey, $consumerSecret, $deliveryUrl, $secret
        );

        return [
            'success' => true,
            'webhooks' => array_merge($registered, $cartWebhooks),
            'abandoned_cart_webhooks' => $cartWebhooks,
            'abandoned_cart_failures' => $cartFailures,
        ];
    }

    /**
     * Best-effort registration of the plugin topics. Never throws.
     *
     * @return array{0: array<int, mixed>, 1: array<int, string>} [registered webhooks, failed topics]
     */
    protected function registerAbandonedCartWebhooks(
        Shop $shop,
        string $storeUrl,
        string $consumerKey,
        string $consumerSecret,
        string $deliveryUrl,
        string $secret,
    ): array {
        $registered = [];
        $failed = [];

        foreach (self::ABANDONED_CART_TOPICS as $topic) {
            try {
                $response = Http::timeout(30)
                    ->withBasicAuth($consumerKey, $consumerSecret)
                    ->post("{$storeUrl}/wp-json/wc/v3/webhooks", [
                        'name' => "Fitness Center - {$topic}",
                        'topic' => $topic,
                        'delivery_url' => $deliveryUrl,
                        'secret' => $secret,
                        'status' => 'active',
                    ]);

                if ($response->successful()) {
                    $registered[] = $response->json();

                    continue;
                }

                $failed[] = $topic;

                Log::warning('WooCommerce abandoned-cart webhook not registered (is the Abandoned Cart Recovery plugin active?)', [
                    'shop_id' => $shop->id,
                    'topic' => $topic,
                    'status' => $response->status(),
                    'error' => $response->json(),
                ]);
            } catch (\Throwable $e) {
                $failed[] = $topic;

                Log::warning('WooCommerce abandoned-cart webhook registration exception', [
                    'shop_id' => $shop->id,
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [$registered, $failed];
    }

    /**
     * Deregister all webhooks from WooCommerce that deliver to this shop's POS
     * endpoint — order and abandoned-cart topics alike.
     */
    public function deregisterWebhooks(Shop $shop): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $baseUrl = rtrim(config('app.url'), '/');
            $deliveryUrl = "{$baseUrl}/api/webhooks/woocommerce/{$shop->uuid}";
            $perPage = 100;

            // The list endpoint pages at 10 by default; with nine POS topics per
            // shop a single default page could miss some of them. Collect every
            // page first, then delete, so deleting cannot shift later pages.
            $ids = [];

            for ($page = 1; $page <= 20; $page++) {
                $response = Http::timeout(30)
                    ->withBasicAuth($consumerKey, $consumerSecret)
                    ->get("{$storeUrl}/wp-json/wc/v3/webhooks", ['per_page' => $perPage, 'page' => $page]);

                if (! $response->successful()) {
                    break;
                }

                $webhooks = (array) $response->json();

                foreach ($webhooks as $webhook) {
                    if (($webhook['delivery_url'] ?? '') === $deliveryUrl && isset($webhook['id'])) {
                        $ids[] = $webhook['id'];
                    }
                }

                if (count($webhooks) < $perPage) {
                    break;
                }
            }

            foreach ($ids as $id) {
                Http::timeout(30)
                    ->withBasicAuth($consumerKey, $consumerSecret)
                    ->delete("{$storeUrl}/wp-json/wc/v3/webhooks/{$id}", ['force' => true]);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Map local status back to WooCommerce status for API updates.
     */
    public function mapToWooCommerceStatus(string $localStatus): string
    {
        return match ($localStatus) {
            'pending' => 'pending',
            'processing' => 'processing',
            'on-hold' => 'on-hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'failed' => 'failed',
            default => 'pending',
        };
    }
}
