# E-Commerce Orders Integration

## Overview

Integrate WooCommerce and Shopify orders into the application. Website orders appear in a dedicated "Website Orders" tab, can be managed (status changes synced back to the API), and can be converted into local sales.

---

## Architecture

### Data Flow

```
                           ┌─────────────────────────────────────────────────┐
                           │              PRIMARY: WEBHOOKS                  │
                           │                                                 │
  WooCommerce/Shopify ──webhook POST──→ /api/webhooks/{platform}/{shop} ──→ │
                           │              Verify Signature                   │
                           │              Dispatch ProcessOrderWebhookJob    │
                           │              Upsert → ecommerce_orders table    │
                           └─────────────────────────────────────────────────┘
                           ┌─────────────────────────────────────────────────┐
                           │            FALLBACK: MANUAL REFRESH             │
                           │                                                 │
  User clicks "Refresh" ──→ Dispatch FetchEcommerceOrdersJob ──→ API call   │
                           │              Upsert → ecommerce_orders table    │
                           └─────────────────────────────────────────────────┘
                                                    │
                                               ecommerce_orders table → UI
                                                    ↓
                               Status change ← Sync back to API ← User Action
                                                    ↓
                               Convert to Sale → sales table ← User fills missing fields
```

### Key Principles

1. **Webhooks are the primary mechanism** — Orders arrive in real-time via webhook from WooCommerce/Shopify. No polling needed.
2. **Manual Refresh as fallback** — A "Refresh" button fetches orders via API for cases where webhooks missed events (downtime, registration failure, etc.).
3. **Local cache** — Orders are stored in an `ecommerce_orders` table so the UI never hits external APIs on page load.
4. **Signature verification** — Every webhook request is validated using HMAC-SHA256 to prevent spoofing.

---

## Database

### New Table: `ecommerce_orders`

| Column | Type | Description |
|---|---|---|
| id | bigint PK | |
| uuid | uuid | Route key |
| shop_id | FK → shops | Which shop this order belongs to |
| platform | string | `woocommerce` or `shopify` |
| platform_order_id | string | Order ID on the platform |
| order_number | string | Human-readable order number (e.g., `#1001`) |
| status | string | `pending`, `processing`, `on-hold`, `completed`, `cancelled`, `refunded`, `failed` |
| payment_method | string | e.g., `cod`, `bacs`, `stripe` |
| payment_status | string | `paid`, `unpaid`, `refunded`, `partially_refunded` |
| currency | string(3) | `USD`, `EUR`, etc. |
| subtotal | decimal(10,2) | |
| discount_total | decimal(10,2) | |
| shipping_total | decimal(10,2) | |
| tax_total | decimal(10,2) | |
| total | decimal(10,2) | |
| customer_name | string | |
| customer_email | string nullable | |
| customer_phone | string nullable | |
| billing_address | json nullable | Full billing address |
| shipping_address | json nullable | Full shipping address |
| line_items | json | Array of `{ platform_product_id, name, sku, quantity, price, total }` |
| notes | text nullable | Customer notes from platform |
| platform_created_at | datetime | When order was created on platform |
| platform_updated_at | datetime nullable | Last update on platform |
| platform_data | json nullable | Raw API response for reference |
| sale_id | FK → sales nullable | Set when converted to a local sale |
| converted_at | datetime nullable | When converted to sale |
| converted_by | FK → users nullable | Who converted it |
| last_synced_at | datetime | Last time data was fetched from API |
| timestamps | | created_at, updated_at |

**Indexes:** `shop_id + platform + platform_order_id` (unique), `status`, `sale_id`

### New Table: `ecommerce_order_items`

| Column | Type | Description |
|---|---|---|
| id | bigint PK | |
| ecommerce_order_id | FK → ecommerce_orders | |
| platform_product_id | string nullable | Product ID on platform |
| platform_line_item_id | string nullable | Line item ID on platform |
| product_id | FK → products nullable | Matched local product (via ProductEcommerceSync) |
| name | string | Product name from platform |
| sku | string nullable | |
| quantity | integer | |
| unit_price | decimal(10,2) | |
| subtotal | decimal(10,2) | |
| total | decimal(10,2) | |
| tax_total | decimal(10,2) default 0 | |
| platform_data | json nullable | Raw line item data |
| timestamps | | |

---

## Models

### `EcommerceOrder`

- Uses UUID route binding.
- **Relationships:** `shop`, `sale` (nullable), `convertedBy` (User), `items` (HasMany → EcommerceOrderItem)
- **Scopes:** `platform($platform)`, `status($status)`, `unconverted()`, `converted()`, `forShop($shopId)`
- **Casts:** `billing_address` → array, `shipping_address` → array, `line_items` → array, `platform_data` → array, `platform_created_at` → datetime, `converted_at` → datetime, `last_synced_at` → datetime
- **Accessors:** `isConverted`, `isCod`, `canBeConverted` (not already converted and not cancelled/refunded)
- **Methods:** `markConverted(Sale $sale, User $user)`, `updatePlatformStatus(string $status)`

### `EcommerceOrderItem`

- **Relationships:** `ecommerceOrder`, `product` (nullable — matched local product)
- **Methods:** `hasMatchedProduct(): bool`

---

## Services

### `WooCommerceOrderSyncService`

Follows the exact same auth pattern as `WooCommerceProductSyncService`.

| Method | API Endpoint | Description |
|---|---|---|
| `fetchOrders(Shop, options)` | `GET /wp-json/wc/v3/orders` | Fetch orders list, params: `status`, `per_page`, `page`, `after`, `before` |
| `fetchSingleOrder(Shop, orderId)` | `GET /wp-json/wc/v3/orders/{id}` | Fetch one order |
| `updateOrderStatus(Shop, orderId, status, note?)` | `PUT /wp-json/wc/v3/orders/{id}` | Update status, body: `{ status: "processing" }` |
| `addOrderNote(Shop, orderId, note)` | `POST /wp-json/wc/v3/orders/{id}/notes` | Add a note to the order |
| `syncOrders(Shop, options)` | — | Fetch + upsert into `ecommerce_orders` table |
| `mapToLocal(array $apiOrder)` | — | Transform WC order data → `ecommerce_orders` columns |

**WooCommerce Order Statuses:** `pending`, `processing`, `on-hold`, `completed`, `cancelled`, `refunded`, `failed`

### `ShopifyOrderSyncService`

Follows the exact same auth pattern as `ShopifyProductSyncService`.

| Method | API Endpoint | Description |
|---|---|---|
| `fetchOrders(Shop, options)` | `GET /admin/api/2024-01/orders.json` | Fetch orders, params: `status=any`, `limit` |
| `fetchSingleOrder(Shop, orderId)` | `GET /admin/api/2024-01/orders/{id}.json` | Fetch one order |
| `updateOrderStatus(Shop, orderId, status)` | Various endpoints | See below |
| `syncOrders(Shop, options)` | — | Fetch + upsert into `ecommerce_orders` table |
| `mapToLocal(array $apiOrder)` | — | Transform Shopify order → `ecommerce_orders` columns |

**Shopify Status Actions (different endpoints per action):**
- Cancel: `POST /admin/api/2024-01/orders/{id}/cancel.json`
- Close: `POST /admin/api/2024-01/orders/{id}/close.json`
- Re-open: `POST /admin/api/2024-01/orders/{id}/open.json`

**Shopify Status Mapping:**
| Shopify `financial_status` + `fulfillment_status` | Local Status |
|---|---|
| `pending` | `pending` |
| `authorized` / `partially_paid` | `on-hold` |
| `paid` + no fulfillment | `processing` |
| `paid` + `fulfilled` | `completed` |
| `refunded` | `refunded` |
| `voided` | `cancelled` |

---

## Status Mapping (Unified)

Both platforms map to a unified set of statuses stored in our `ecommerce_orders.status`:

| Unified Status | WooCommerce | Shopify | Badge Color |
|---|---|---|---|
| `pending` | pending | open + pending | warning |
| `processing` | processing | open + paid | info |
| `on-hold` | on-hold | open + authorized | secondary |
| `completed` | completed | closed + fulfilled | success |
| `cancelled` | cancelled | cancelled | danger |
| `refunded` | refunded | open + refunded | dark |
| `failed` | failed | — | danger |

### Enum: `EcommerceOrderStatus`

```php
enum EcommerceOrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case OnHold = 'on-hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
```

---

## Webhooks

### Overview

Webhooks provide **real-time** order notifications. When an order is created, updated, or cancelled on WooCommerce/Shopify, the platform sends a POST request to our endpoint with the order data. This eliminates the need for polling.

### Webhook Endpoints (Public API Routes)

These routes are **outside** `auth:sanctum` middleware since they receive requests from external platforms.

```php
// routes/api.php — public webhook routes
Route::prefix('webhooks')->group(function () {
    Route::post('/woocommerce/{shop:uuid}', [WebhookController::class, 'woocommerce'])->name('webhooks.woocommerce');
    Route::post('/shopify/{shop:uuid}', [WebhookController::class, 'shopify'])->name('webhooks.shopify');
});
```

**Webhook URLs (what gets registered on the platform):**
- WooCommerce: `https://your-app.test/api/webhooks/woocommerce/{shop-uuid}`
- Shopify: `https://your-app.test/api/webhooks/shopify/{shop-uuid}`

### Signature Verification

| Platform | Header | Algorithm | Payload |
|---|---|---|---|
| WooCommerce | `X-WC-Webhook-Signature` | Base64(HMAC-SHA256(body, secret)) | Raw request body |
| Shopify | `X-Shopify-Hmac-Sha256` | Base64(HMAC-SHA256(body, secret)) | Raw request body |

```php
// Verification logic (shared pattern)
$computedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));
$isValid = hash_equals($computedSignature, $receivedSignature);
```

### Webhook Secret Storage

Stored in the shop's integration config (same `settings` JSON structure):

```php
// WooCommerce — settings.integrations.woocommerce
[
    'store_url' => '...',
    'consumer_key' => '...',      // encrypted
    'consumer_secret' => '...',   // encrypted
    'webhook_secret' => '...',    // encrypted — used to verify incoming webhooks
    'enabled' => true,
]

// Shopify — settings.integrations.shopify
[
    'shop_domain' => '...',
    'access_token' => '...',      // encrypted
    'webhook_secret' => '...',    // encrypted — Shopify's HMAC secret
    'enabled' => true,
]
```

### Webhook Registration

Webhooks are registered **automatically when the shop form is saved** — inside the existing `ShopService::create()` / `ShopService::update()` methods. No separate page or button is needed.

**Trigger points (in `ShopService`):**

| Scenario | Action |
|---|---|
| Integration **enabled + saved** (new or credentials changed) | Generate webhook secret → store encrypted in `settings.integrations.{platform}.webhook_secret` → register webhooks via platform API |
| Integration **disabled + saved** (was previously enabled) | Deregister webhooks via platform API → remove webhook secret from config |
| Credentials **unchanged** (edit form saved without touching keys) | Skip — no webhook action needed |
| Integration **enabled but webhook registration fails** | Log warning, save config anyway — user can retry via "Re-register Webhooks" button on show page |

**Show page enhancement:**
- Display webhook status (registered / not registered) alongside connection status
- Add a "Re-register Webhooks" button for cases where initial registration failed or webhooks need to be refreshed (e.g., after domain change)
- Route: `POST /shops/{shop}/webhooks/register` → `ShopController@registerWebhooks`

**`ShopService` integration flow (pseudocode):**
```php
// In ShopService::update() — after saving integration config
foreach (['woocommerce', 'shopify'] as $platform) {
    $wasEnabled = $oldConfig[$platform]['enabled'] ?? false;
    $isEnabled  = $newConfig[$platform]['enabled'] ?? false;

    if ($isEnabled && !$wasEnabled) {
        // Newly enabled → generate secret + register webhooks
        $secret = WebhookRegistrationService::generateSecret();
        $shop->setIntegrationConfig($platform, [...$config, 'webhook_secret' => Crypt::encrypt($secret)]);
        dispatch(new RegisterWebhooksJob($shop, $platform, $secret));
    } elseif (!$isEnabled && $wasEnabled) {
        // Disabled → deregister webhooks
        dispatch(new DeregisterWebhooksJob($shop, $platform));
    }
}
```

**WooCommerce — register via REST API:**
```
POST /wp-json/wc/v3/webhooks
{
    "name": "Order created",
    "topic": "order.created",
    "delivery_url": "https://your-app.test/api/webhooks/woocommerce/{shop-uuid}",
    "secret": "<generated-secret>",
    "status": "active"
}
// Repeat for: order.updated, order.deleted
```

**Shopify — register via Admin API:**
```
POST /admin/api/2024-01/webhooks.json
{
    "webhook": {
        "topic": "orders/create",
        "address": "https://your-app.test/api/webhooks/shopify/{shop-uuid}",
        "format": "json"
    }
}
// Repeat for: orders/updated, orders/cancelled, orders/fulfilled, orders/paid
```

### Webhook Topics

| WooCommerce Topic | Shopify Topic | Action |
|---|---|---|
| `order.created` | `orders/create` | Upsert new order into `ecommerce_orders` |
| `order.updated` | `orders/updated` | Update existing order data + status |
| `order.deleted` | — | Soft-delete or mark as removed |
| — | `orders/cancelled` | Update status to `cancelled` |
| — | `orders/fulfilled` | Update status to `completed` |
| — | `orders/paid` | Update payment status to `paid` |

### WebhookController Flow

```
1. Receive POST request
2. Find Shop by UUID
3. Get webhook_secret from integration config
4. Verify HMAC signature → reject with 401 if invalid
5. Determine event topic from headers:
   - WooCommerce: X-WC-Webhook-Topic header
   - Shopify: X-Shopify-Topic header
6. Dispatch ProcessOrderWebhookJob(shop, topic, payload) → queued
7. Return 200 OK immediately (platforms expect fast response)
```

### ProcessOrderWebhookJob

Queued job that processes the webhook payload:

**Properties:** `timeout = 30`, `tries = 3`, `backoff = [5, 15, 30]`

```
1. Determine platform from job data
2. Use appropriate sync service to mapToLocal(payload)
3. Upsert into ecommerce_orders table
4. Match line items to local products (via SKU or ProductEcommerceSync)
5. If order.deleted → soft-delete the local order record
```

### Webhook Registration Service

| Method | Description |
|---|---|
| `registerWebhooks(Shop, platform)` | Register all required webhook topics for a shop |
| `deregisterWebhooks(Shop, platform)` | Remove all webhooks when integration is disabled |
| `listWebhooks(Shop, platform)` | List currently registered webhooks |
| `generateSecret()` | Generate a random webhook secret |
| `getDeliveryUrl(Shop, platform)` | Build the webhook URL using `route()` |

---

## Controller: `EcommerceOrderController`

| Method | Route | Description |
|---|---|---|
| `index` | `GET /orders` | List all e-commerce orders with filters (shop, platform, status, search) |
| `show` | `GET /orders/{order:uuid}` | View order details |
| `refresh` | `POST /orders/refresh` | Fetch latest orders from API for selected shop. Dispatches `FetchEcommerceOrdersJob` |
| `updateStatus` | `POST /orders/{order:uuid}/status` | Change status and sync back to API |
| `convertToSale` | `GET /orders/{order:uuid}/convert` | Show conversion form with pre-filled data |
| `storeConversion` | `POST /orders/{order:uuid}/convert` | Create sale from order, link them |

---

## Jobs

### `ProcessOrderWebhookJob` (Primary — triggered by webhooks)

Processes a single incoming webhook payload. This is the main path for receiving orders.

**Properties:** `timeout = 30`, `tries = 3`, `backoff = [5, 15, 30]`

1. Determine platform (WooCommerce/Shopify) and topic from job data
2. Use appropriate sync service to `mapToLocal(payload)`
3. Upsert into `ecommerce_orders` table
4. Match line items to local products via SKU or `ProductEcommerceSync`
5. Handle deletions/cancellations appropriately

### `FetchEcommerceOrdersJob` (Fallback — triggered by manual Refresh)

Lightweight dispatcher (same pattern as `SyncProductsJob`):
1. Fetches orders from the API (one API call, ~2-5s)
2. Upserts into `ecommerce_orders` table
3. Matches line items to local products via `ProductEcommerceSync` or SKU

**Properties:** `timeout = 60`, `tries = 3`

### `SyncOrderStatusJob`

Syncs a single order's status change back to the platform API.

**Properties:** `timeout = 30`, `tries = 3`, `backoff = 10`

---

## Convert to Sale Flow

### Pre-fill from Order

When converting an e-commerce order to a sale:

| Sale Field | Source |
|---|---|
| `shop_id` | `ecommerce_order.shop_id` |
| `customer_id` | Matched by email/phone if customer exists, otherwise show walk-in fields |
| `walk_in_customer_name` | `ecommerce_order.customer_name` |
| `walk_in_customer_email` | `ecommerce_order.customer_email` |
| `walk_in_customer_phone` | `ecommerce_order.customer_phone` |
| `items` | Matched products from `ecommerce_order_items` (via `product_id`) |
| `delivery_location` | From shipping address |
| `delivery_fee` | `ecommerce_order.shipping_total` |
| `discount_amount` | `ecommerce_order.discount_total` |
| `tax_amount` | `ecommerce_order.tax_total` |
| `is_cod` | `true` if `payment_method` is `cod` |
| `notes` | `"Converted from {Platform} order #{order_number}"` |

### User Must Fill

- `source_id` — pre-select or auto-create "WooCommerce" / "Shopify" SaleSource
- `register_id` — must select active cash register
- `payment_method` — pre-select based on order's payment method
- Verify/adjust item prices and quantities
- Select delivery company (if applicable)

### COD Logic

1. When an order's `payment_method` is `cod`:
   - Create sale with `is_cod = true`, `payment_status = 'unpaid'`, `status = 'pending'`
   - Update platform order status to `processing`
2. When COD sale is completed and payment collected:
   - Update platform order status to `completed`

### Conversion Steps (in transaction)

1. Validate all items have matched local products
2. Create `Sale` record with pre-filled data
3. Create `SaleItem` records for each line item
4. Decrement stock for each product
5. Mark `ecommerce_order.sale_id`, `converted_at`, `converted_by`
6. If COD → set platform status to `processing` via `SyncOrderStatusJob`
7. If already paid → set platform status to `completed`, sale status to `completed`

---

## Views

### `resources/views/orders/index.blade.php`

- Statistics cards: Total Orders, Pending, Processing, Completed
- Filter bar: Shop dropdown, Platform dropdown, Status dropdown, Search (order number/customer name)
- Refresh button → dispatches fetch job
- Table columns: Order #, Platform, Customer, Items (count), Total, Payment, Status, Date, Actions
- Actions: View, Convert to Sale (if unconverted + not cancelled), Change Status dropdown

### `resources/views/orders/show.blade.php`

- Order details card: order number, platform, dates, status badge
- Customer info card: name, email, phone, addresses
- Line items table: product name, SKU, qty, price, total, matched local product indicator
- Status history / notes section
- Action buttons: Change Status, Convert to Sale
- If converted: link to the associated sale

### `resources/views/orders/convert.blade.php`

- Re-uses the sale creation form structure but pre-populated
- Read-only section: order details, line items from platform
- Editable section: customer (select existing or use walk-in), source, register, payment method, delivery
- Item mapping section: shows each order item → matched local product (warn if unmatched)
- Submit: "Create Sale from Order"

---

## Routes

```php
// routes/web.php — inside auth middleware group
Route::prefix('orders')->name('orders.')->group(function () {
    Route::get('/', [EcommerceOrderController::class, 'index'])->name('index');
    Route::post('/refresh', [EcommerceOrderController::class, 'refresh'])->name('refresh');
    Route::get('/{order:uuid}', [EcommerceOrderController::class, 'show'])->name('show');
    Route::post('/{order:uuid}/status', [EcommerceOrderController::class, 'updateStatus'])->name('updateStatus');
    Route::get('/{order:uuid}/convert', [EcommerceOrderController::class, 'convertToSale'])->name('convert');
    Route::post('/{order:uuid}/convert', [EcommerceOrderController::class, 'storeConversion'])->name('storeConversion');
});
```

---

## Files to Create

| File | Type |
|---|---|
| `database/migrations/xxxx_create_ecommerce_orders_table.php` | Migration |
| `database/migrations/xxxx_create_ecommerce_order_items_table.php` | Migration |
| `app/Enums/EcommerceOrderStatus.php` | Enum |
| `app/Models/EcommerceOrder.php` | Model |
| `app/Models/EcommerceOrderItem.php` | Model |
| `app/Services/Integration/WooCommerceOrderSyncService.php` | Service |
| `app/Services/Integration/ShopifyOrderSyncService.php` | Service |
| `app/Services/Integration/WebhookRegistrationService.php` | Service |
| `app/Http/Controllers/WebhookController.php` | Controller (API) |
| `app/Http/Middleware/VerifyWebhookSignature.php` | Middleware |
| `app/Jobs/ProcessOrderWebhookJob.php` | Job |
| `app/Jobs/RegisterWebhooksJob.php` | Job |
| `app/Jobs/DeregisterWebhooksJob.php` | Job |
| `app/Jobs/FetchEcommerceOrdersJob.php` | Job |
| `app/Jobs/SyncOrderStatusJob.php` | Job |
| `app/Actions/ConvertEcommerceOrderToSale.php` | Action |
| `app/Http/Controllers/EcommerceOrderController.php` | Controller |
| `app/Http/Requests/ConvertOrderToSaleRequest.php` | Form Request |
| `app/Http/Requests/UpdateOrderStatusRequest.php` | Form Request |
| `app/Policies/EcommerceOrderPolicy.php` | Policy |
| `resources/views/orders/index.blade.php` | View |
| `resources/views/orders/show.blade.php` | View |
| `resources/views/orders/convert.blade.php` | View |
| `tests/Feature/EcommerceOrderTest.php` | Test |
| `tests/Feature/WebhookTest.php` | Test |

## Files to Modify

| File | Change |
|---|---|
| `routes/api.php` | Add public webhook routes (outside `auth:sanctum`) |
| `routes/web.php` | Add order routes + `shops/{shop}/webhooks/register` route |
| `app/Services/ShopService.php` | Add webhook registration/deregistration on integration enable/disable |
| `app/Http/Controllers/ShopController.php` | Add `registerWebhooks()` method |
| `resources/views/shops/show.blade.php` | Add webhook status display + "Re-register Webhooks" button |
| `resources/views/layouts/app.blade.php` | Add "Orders" nav item (or sidebar link) |
| `app/Providers/AppServiceProvider.php` | Register EcommerceOrder policy |

---

## Implementation Order

1. **Phase 1 — Data Layer:** Migration, Enum, Models
2. **Phase 2 — API Services:** WooCommerceOrderSyncService, ShopifyOrderSyncService
3. **Phase 3 — Webhooks:** WebhookController, VerifyWebhookSignature middleware, ProcessOrderWebhookJob, WebhookRegistrationService, public API routes
4. **Phase 4 — Fallback Jobs:** FetchEcommerceOrdersJob, SyncOrderStatusJob
5. **Phase 5 — Controller & Routes:** EcommerceOrderController, web routes, form requests, policy
6. **Phase 6 — Views:** index, show, convert
7. **Phase 7 — Convert to Sale:** ConvertEcommerceOrderToSale action, COD flow
8. **Phase 8 — Tests:** Feature tests for webhooks, sync, status update, conversion
