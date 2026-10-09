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

class ShopifyOrderSyncService
{
    /**
     * Fetch orders from Shopify API.
     *
     * @return array{success: bool, orders?: array, message?: string}
     */
    public function fetchOrders(Shop $shop, array $options = []): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            $params = [
                'limit' => $options['limit'] ?? 250,
                'status' => $options['status'] ?? 'any',
                'order' => 'created_at desc',
            ];

            if (! empty($options['since_id'])) {
                $params['since_id'] = $options['since_id'];
            }

            if (! empty($options['created_at_min'])) {
                $params['created_at_min'] = $options['created_at_min'];
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->get("https://{$shopDomain}/admin/api/2024-01/orders.json", $params);

            if (! $response->successful()) {
                Log::error('Shopify Orders API request failed', [
                    'shop_id' => $shop->id,
                    'status_code' => $response->status(),
                    'error' => $response->json() ?? $response->body(),
                ]);

                return ['success' => false, 'message' => 'API request failed: ' . $response->status()];
            }

            return ['success' => true, 'orders' => $response->json('orders') ?? []];
        } catch (\Exception $e) {
            Log::error('Shopify Orders fetch exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetch a single order from Shopify.
     *
     * @return array{success: bool, order?: array, message?: string}
     */
    public function fetchSingleOrder(Shop $shop, string $orderId): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->get("https://{$shopDomain}/admin/api/2024-01/orders/{$orderId}.json");

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'API request failed: ' . $response->status()];
            }

            return ['success' => true, 'order' => $response->json('order')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update an order's status on Shopify.
     * Shopify uses different endpoints for different status changes.
     *
     * @return array{success: bool, message?: string}
     */
    public function updateOrderStatus(Shop $shop, string $orderId, string $status): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);
            $baseUrl = "https://{$shopDomain}/admin/api/2024-01/orders/{$orderId}";

            $headers = [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ];

            $response = match ($status) {
                'cancelled' => Http::timeout(30)->withHeaders($headers)
                    ->post("{$baseUrl}/cancel.json"),
                'completed' => Http::timeout(30)->withHeaders($headers)
                    ->post("{$baseUrl}/close.json"),
                'processing' => Http::timeout(30)->withHeaders($headers)
                    ->post("{$baseUrl}/open.json"),
                default => Http::timeout(30)->withHeaders($headers)
                    ->put("{$baseUrl}.json", ['order' => ['note' => "Status changed to: {$status}"]]),
            };

            if (! $response->successful()) {
                Log::error('Shopify order status update failed', [
                    'shop_id' => $shop->id,
                    'order_id' => $orderId,
                    'status' => $status,
                    'response_status' => $response->status(),
                    'error' => $response->json(),
                ]);

                return ['success' => false, 'message' => 'Status update failed: ' . $response->status()];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Shopify order status update exception', [
                'shop_id' => $shop->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync edited order items to Shopify using the Admin GraphQL Order Edit API.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{success: bool, message?: string}
     */
    public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        if (empty($config['access_token'])) {
            return ['success' => false, 'message' => 'Shopify access token is missing.'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);
            $beginResult = $this->beginShopifyOrderEdit($shopDomain, $accessToken, $this->shopifyOrderGid($order));

            if (! ($beginResult['success'] ?? false)) {
                return $beginResult;
            }

            $calculatedOrderId = $beginResult['calculated_order_id'];
            $lineItemMap = $beginResult['line_item_map'];
            $operations = $this->buildShopifyOrderEditOperations($shop, $order, $items, $lineItemMap);

            if (empty($operations)) {
                return ['success' => true];
            }

            foreach ($operations as $operation) {
                $operationResult = match ($operation['type']) {
                    'set_quantity' => $this->setShopifyOrderEditQuantity(
                        $shopDomain,
                        $accessToken,
                        $calculatedOrderId,
                        $operation['line_item_id'],
                        $operation['quantity']
                    ),
                    'add_variant' => $this->addShopifyOrderEditVariant(
                        $shopDomain,
                        $accessToken,
                        $calculatedOrderId,
                        $operation['variant_id'],
                        $operation['quantity']
                    ),
                    default => ['success' => false, 'message' => 'Unsupported Shopify order edit operation.'],
                };

                if (! ($operationResult['success'] ?? false)) {
                    return $operationResult;
                }
            }

            $commitResult = $this->commitShopifyOrderEdit(
                $shopDomain,
                $accessToken,
                $calculatedOrderId,
                $this->shopifyOrderEditNote($order, $shippingTotal)
            );

            if (! ($commitResult['success'] ?? false)) {
                return $commitResult;
            }

            $freshOrder = $this->fetchSingleOrder($shop, $order->platform_order_id);
            if ($freshOrder['success'] ?? false) {
                $this->upsertOrder($shop, $freshOrder['order']);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Shopify order item sync exception', [
                'shop_id' => $shop->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync orders from Shopify into local ecommerce_orders table.
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
     * @return array{success: bool, calculated_order_id?: string, line_item_map?: array<string, string>, message?: string}
     */
    protected function beginShopifyOrderEdit(string $shopDomain, string $accessToken, string $orderGid): array
    {
        $query = <<<'GRAPHQL'
mutation orderEditBegin($id: ID!) {
  orderEditBegin(id: $id) {
    calculatedOrder {
      id
      lineItems(first: 250) {
        edges {
          node {
            id
            originalLineItem {
              id
            }
          }
        }
      }
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $result = $this->shopifyGraphQl($shopDomain, $accessToken, $query, ['id' => $orderGid]);
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $payload = $result['data']['orderEditBegin'] ?? [];
        $userErrors = $payload['userErrors'] ?? [];
        if (! empty($userErrors)) {
            return ['success' => false, 'message' => $this->formatShopifyUserErrors($userErrors)];
        }

        $calculatedOrder = $payload['calculatedOrder'] ?? null;
        if (! $calculatedOrder) {
            return ['success' => false, 'message' => 'Shopify did not return a calculated order for editing.'];
        }

        $lineItemMap = [];
        foreach ($calculatedOrder['lineItems']['edges'] ?? [] as $edge) {
            $node = $edge['node'] ?? [];
            $originalLineItemId = $node['originalLineItem']['id'] ?? null;

            if ($originalLineItemId && ! empty($node['id'])) {
                $lineItemMap[$originalLineItemId] = $node['id'];
            }
        }

        return [
            'success' => true,
            'calculated_order_id' => $calculatedOrder['id'],
            'line_item_map' => $lineItemMap,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, string>  $lineItemMap
     * @return array<int, array<string, mixed>>
     */
    protected function buildShopifyOrderEditOperations(Shop $shop, EcommerceOrder $order, array $items, array $lineItemMap): array
    {
        $order->loadMissing('items');
        $submittedByOrderItemId = collect($items)
            ->filter(fn(array $item): bool => ! empty($item['order_item_id']))
            ->keyBy(fn(array $item): int => (int) $item['order_item_id']);

        $operations = [];

        foreach ($order->items as $orderItem) {
            $submittedItem = $submittedByOrderItemId->get($orderItem->id);
            $calculatedLineItemId = $this->calculatedShopifyLineItemId($orderItem, $lineItemMap);

            if (! $submittedItem) {
                if ($calculatedLineItemId) {
                    $operations[] = [
                        'type' => 'set_quantity',
                        'line_item_id' => $calculatedLineItemId,
                        'quantity' => 0,
                    ];
                }

                continue;
            }

            $sameProduct = (int) ($submittedItem['product_id'] ?? 0) === (int) ($orderItem->product_id ?? 0);

            if ($sameProduct && $calculatedLineItemId) {
                if ((int) $submittedItem['quantity'] !== (int) $orderItem->quantity) {
                    $operations[] = [
                        'type' => 'set_quantity',
                        'line_item_id' => $calculatedLineItemId,
                        'quantity' => (int) $submittedItem['quantity'],
                    ];
                }

                continue;
            }

            if ($calculatedLineItemId) {
                $operations[] = [
                    'type' => 'set_quantity',
                    'line_item_id' => $calculatedLineItemId,
                    'quantity' => 0,
                ];
            }

            $operations[] = [
                'type' => 'add_variant',
                'variant_id' => $this->resolveShopifyVariantGid($shop, (int) $submittedItem['product_id']),
                'quantity' => (int) $submittedItem['quantity'],
            ];
        }

        foreach ($items as $item) {
            if (! empty($item['order_item_id'])) {
                continue;
            }

            $operations[] = [
                'type' => 'add_variant',
                'variant_id' => $this->resolveShopifyVariantGid($shop, (int) $item['product_id']),
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $operations;
    }

    protected function setShopifyOrderEditQuantity(string $shopDomain, string $accessToken, string $calculatedOrderId, string $lineItemId, int $quantity): array
    {
        $query = <<<'GRAPHQL'
mutation orderEditSetQuantity($id: ID!, $lineItemId: ID!, $quantity: Int!) {
  orderEditSetQuantity(id: $id, lineItemId: $lineItemId, quantity: $quantity) {
    calculatedLineItem {
      id
      quantity
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $result = $this->shopifyGraphQl($shopDomain, $accessToken, $query, [
            'id' => $calculatedOrderId,
            'lineItemId' => $lineItemId,
            'quantity' => $quantity,
        ]);

        return $this->shopifyMutationResult($result, 'orderEditSetQuantity');
    }

    protected function addShopifyOrderEditVariant(string $shopDomain, string $accessToken, string $calculatedOrderId, string $variantId, int $quantity): array
    {
        $query = <<<'GRAPHQL'
mutation orderEditAddVariant($id: ID!, $variantId: ID!, $quantity: Int!) {
  orderEditAddVariant(id: $id, variantId: $variantId, quantity: $quantity, allowDuplicates: true) {
    calculatedLineItem {
      id
      quantity
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $result = $this->shopifyGraphQl($shopDomain, $accessToken, $query, [
            'id' => $calculatedOrderId,
            'variantId' => $variantId,
            'quantity' => $quantity,
        ]);

        return $this->shopifyMutationResult($result, 'orderEditAddVariant');
    }

    protected function commitShopifyOrderEdit(string $shopDomain, string $accessToken, string $calculatedOrderId, string $staffNote): array
    {
        $query = <<<'GRAPHQL'
mutation orderEditCommit($id: ID!, $staffNote: String!) {
  orderEditCommit(id: $id, notifyCustomer: false, staffNote: $staffNote) {
    order {
      id
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $result = $this->shopifyGraphQl($shopDomain, $accessToken, $query, [
            'id' => $calculatedOrderId,
            'staffNote' => $staffNote,
        ]);

        return $this->shopifyMutationResult($result, 'orderEditCommit');
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>, message?: string}
     */
    protected function shopifyGraphQl(string $shopDomain, string $accessToken, string $query, array $variables = []): array
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->post("https://{$shopDomain}/admin/api/2024-01/graphql.json", [
                'query' => $query,
                'variables' => $variables,
            ]);

        if (! $response->successful()) {
            return ['success' => false, 'message' => 'Shopify GraphQL request failed: ' . $response->status()];
        }

        $payload = $response->json();
        if (! empty($payload['errors'])) {
            return ['success' => false, 'message' => collect($payload['errors'])->pluck('message')->implode(' ')];
        }

        return ['success' => true, 'data' => $payload['data'] ?? []];
    }

    /**
     * @param  array{success: bool, data?: array<string, mixed>, message?: string}  $result
     * @return array{success: bool, message?: string}
     */
    protected function shopifyMutationResult(array $result, string $mutationName): array
    {
        if (! ($result['success'] ?? false)) {
            return $result;
        }

        $userErrors = $result['data'][$mutationName]['userErrors'] ?? [];
        if (! empty($userErrors)) {
            return ['success' => false, 'message' => $this->formatShopifyUserErrors($userErrors)];
        }

        return ['success' => true];
    }

    /**
     * @param  array<int, array<string, mixed>>  $userErrors
     */
    protected function formatShopifyUserErrors(array $userErrors): string
    {
        return collect($userErrors)
            ->pluck('message')
            ->filter()
            ->implode(' ');
    }

    protected function calculatedShopifyLineItemId(EcommerceOrderItem $orderItem, array $lineItemMap): ?string
    {
        $originalLineItemId = $orderItem->platform_data['admin_graphql_api_id'] ?? null;

        if (! $originalLineItemId && $orderItem->platform_line_item_id) {
            $originalLineItemId = 'gid://shopify/LineItem/' . $orderItem->platform_line_item_id;
        }

        return $originalLineItemId ? ($lineItemMap[$originalLineItemId] ?? null) : null;
    }

    protected function resolveShopifyVariantGid(Shop $shop, int $productId): string
    {
        $sync = ProductEcommerceSync::where('shop_id', $shop->id)
            ->where('platform', 'shopify')
            ->where('product_id', $productId)
            ->first();

        $variantId = $sync?->platform_data['variant_id'] ?? $sync?->platform_product_id;

        if (! $variantId) {
            $productName = Product::find($productId)?->name ?? 'Selected product';

            throw new \RuntimeException("{$productName} is not synced with Shopify and cannot be added to the website order.");
        }

        return str_starts_with((string) $variantId, 'gid://')
            ? (string) $variantId
            : 'gid://shopify/ProductVariant/' . $variantId;
    }

    protected function shopifyOrderGid(EcommerceOrder $order): string
    {
        $orderGid = $order->platform_data['admin_graphql_api_id'] ?? null;

        return $orderGid ?: 'gid://shopify/Order/' . $order->platform_order_id;
    }

    protected function shopifyOrderEditNote(EcommerceOrder $order, ?float $shippingTotal): string
    {
        $note = 'Order items adjusted during POS conversion.';

        if ($shippingTotal !== null && (float) $order->shipping_total !== $shippingTotal) {
            $note .= ' POS delivery fee selected: ' . $order->currency . ' ' . number_format($shippingTotal, 2);
        }

        return $note;
    }

    /**
     * Upsert a single Shopify order into the local database.
     */
    public function upsertOrder(Shop $shop, array $apiOrder): EcommerceOrder
    {
        $mapped = $this->mapToLocal($apiOrder);

        $order = EcommerceOrder::withTrashed()->updateOrCreate(
            [
                'shop_id' => $shop->id,
                'platform' => 'shopify',
                'platform_order_id' => (string) $apiOrder['id'],
            ],
            array_merge($mapped, [
                'shop_id' => $shop->id,
                'last_synced_at' => now(),
            ])
        );

        if ($order->trashed()) {
            $order->restore();
        }

        $this->syncLineItems($order, $apiOrder['line_items'] ?? [], $shop);

        return $order;
    }

    /**
     * Map Shopify API order to local columns.
     *
     * @return array<string, mixed>
     */
    public function mapToLocal(array $apiOrder): array
    {
        $customer = $apiOrder['customer'] ?? [];
        $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
        if (empty($customerName)) {
            $billing = $apiOrder['billing_address'] ?? [];
            $customerName = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        }

        return [
            'platform' => 'shopify',
            'platform_order_id' => (string) $apiOrder['id'],
            'order_number' => $apiOrder['name'] ?? '#' . $apiOrder['order_number'] ?? (string) $apiOrder['id'],
            'status' => $this->mapStatus($apiOrder),
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'currency' => $apiOrder['currency'] ?? 'KES',
            'subtotal' => floatval($apiOrder['subtotal_price'] ?? 0),
            'discount_total' => floatval($apiOrder['total_discounts'] ?? 0),
            'shipping_total' => floatval($apiOrder['total_shipping_price_set']['shop_money']['amount'] ?? 0),
            'tax_total' => floatval($apiOrder['total_tax'] ?? 0),
            'total' => floatval($apiOrder['total_price'] ?? 0),
            'customer_name' => $customerName ?: null,
            'customer_email' => $apiOrder['email'] ?? $customer['email'] ?? null,
            'customer_phone' => $apiOrder['phone'] ?? $customer['phone'] ?? null,
            'billing_address' => $apiOrder['billing_address'] ?? null,
            'shipping_address' => $apiOrder['shipping_address'] ?? null,
            'notes' => $apiOrder['note'] ?? null,
            'platform_created_at' => $apiOrder['created_at'] ?? null,
            'platform_updated_at' => $apiOrder['updated_at'] ?? null,
            'platform_data' => $apiOrder,
        ];
    }

    /**
     * Map Shopify order to unified status using financial + fulfillment status.
     */
    protected function mapStatus(array $apiOrder): string
    {
        $financialStatus = $apiOrder['financial_status'] ?? 'pending';
        $fulfillmentStatus = $apiOrder['fulfillment_status'] ?? null;
        $cancelledAt = $apiOrder['cancelled_at'] ?? null;

        if ($cancelledAt) {
            return 'cancelled';
        }

        if ($financialStatus === 'refunded') {
            return 'refunded';
        }

        if ($financialStatus === 'voided') {
            return 'cancelled';
        }

        if ($fulfillmentStatus === 'fulfilled') {
            return 'completed';
        }

        if (in_array($financialStatus, ['authorized', 'partially_paid'])) {
            return 'on-hold';
        }

        if ($financialStatus === 'paid') {
            return 'processing';
        }

        return 'pending';
    }

    /**
     * Map Shopify financial_status to local payment status.
     */
    protected function mapPaymentStatus(string $financialStatus): string
    {
        return match ($financialStatus) {
            'paid' => 'paid',
            'refunded', 'partially_refunded' => 'refunded',
            'authorized', 'partially_paid' => 'unpaid',
            default => 'unpaid',
        };
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
                    'name' => $item['name'] ?? $item['title'] ?? 'Unknown Product',
                    'sku' => $item['sku'] ?? null,
                    'quantity' => intval($item['quantity'] ?? 1),
                    'unit_price' => floatval($item['price'] ?? 0),
                    'subtotal' => floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 1),
                    'total' => floatval($item['price'] ?? 0) * intval($item['quantity'] ?? 1),
                    'tax_total' => collect($item['tax_lines'] ?? [])->sum('price'),
                    'platform_data' => $item,
                ]
            );

            $processedIds[] = $orderItem->id;
        }

        $removedIds = array_diff($existingItemIds, $processedIds);
        if (! empty($removedIds)) {
            EcommerceOrderItem::whereIn('id', $removedIds)->delete();
        }
    }

    /**
     * Match a platform product to a local product via ProductEcommerceSync or SKU.
     */
    protected function matchLocalProduct(Shop $shop, string $platformProductId, ?string $sku): ?int
    {
        if ($platformProductId) {
            $sync = ProductEcommerceSync::where('shop_id', $shop->id)
                ->where('platform', 'shopify')
                ->where('platform_product_id', $platformProductId)
                ->first();

            if ($sync) {
                return $sync->product_id;
            }
        }

        if ($sku) {
            $product = Product::where('sku', $sku)->first();

            return $product?->id;
        }

        return null;
    }

    /**
     * Register webhooks on Shopify for order events.
     *
     * @return array{success: bool, webhooks?: array, message?: string}
     */
    public function registerWebhooks(Shop $shop): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);
            $baseUrl = rtrim(config('app.url'), '/');
            $deliveryUrl = "{$baseUrl}/api/webhooks/shopify/{$shop->uuid}";

            $headers = [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ];

            $topics = [
                'orders/create',
                'orders/updated',
                'orders/cancelled',
                'orders/fulfilled',
                'orders/paid',
            ];
            $registered = [];

            foreach ($topics as $topic) {
                $response = Http::timeout(30)->withHeaders($headers)
                    ->post("https://{$shopDomain}/admin/api/2024-01/webhooks.json", [
                        'webhook' => [
                            'topic' => $topic,
                            'address' => $deliveryUrl,
                            'format' => 'json',
                        ],
                    ]);

                if ($response->successful()) {
                    $registered[] = $response->json('webhook');
                } else {
                    Log::warning('Shopify webhook registration failed', [
                        'shop_id' => $shop->id,
                        'topic' => $topic,
                        'status' => $response->status(),
                        'error' => $response->json(),
                    ]);
                }
            }

            return ['success' => true, 'webhooks' => $registered];
        } catch (\Exception $e) {
            Log::error('Shopify webhook registration exception', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Deregister all webhooks from Shopify.
     */
    public function deregisterWebhooks(Shop $shop): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            $headers = [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ];

            $response = Http::timeout(30)->withHeaders($headers)
                ->get("https://{$shopDomain}/admin/api/2024-01/webhooks.json");

            if ($response->successful()) {
                $baseUrl = rtrim(config('app.url'), '/');
                $deliveryUrl = "{$baseUrl}/api/webhooks/shopify/{$shop->uuid}";

                foreach ($response->json('webhooks') ?? [] as $webhook) {
                    if (($webhook['address'] ?? '') === $deliveryUrl) {
                        Http::timeout(30)->withHeaders($headers)
                            ->delete("https://{$shopDomain}/admin/api/2024-01/webhooks/{$webhook['id']}.json");
                    }
                }
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Normalize Shopify domain (strip protocol, trailing slashes).
     */
    protected function normalizeShopDomain(string $domain): string
    {
        $domain = preg_replace('#^https?://#', '', $domain);

        return rtrim($domain, '/');
    }
}
