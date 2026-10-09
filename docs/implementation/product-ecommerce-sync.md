# Product E-commerce Synchronization

## Overview

The Product E-commerce Synchronization system enables bidirectional product syncing between the local inventory system and external e-commerce platforms (WooCommerce and Shopify). Products are managed using unique SKU numbers that are shared across all platforms, while allowing platform-specific names (aliases) for each e-commerce store.

### Key Features

- ✅ **Unique SKU Management**: Auto-generate SKUs if not provided
- ✅ **Bidirectional Sync**: Push products TO platforms and pull products FROM platforms
- ✅ **Platform Aliases**: Use different product names on each platform
- ✅ **Inventory Sync**: Sync stock levels across platforms
- ✅ **Change Tracking**: Monitor sync status and history
- ✅ **Bulk & Individual Sync**: Sync all products or specific items
- ✅ **Auto-sync Support**: Enable automatic synchronization
- ✅ **Error Handling**: Detailed error logging and retry mechanisms

---

## Database Schema

### `products` Table
Already exists with unique SKU field:
```php
$table->string('sku')->unique();
```

### `product_ecommerce_sync` Table

Manages synchronization between local products and e-commerce platforms:

```php
Schema::create('product_ecommerce_sync', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->unique();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
    $table->string('platform'); // woocommerce, shopify
    $table->string('platform_product_id')->nullable();
    $table->string('platform_name')->nullable(); // Alias
    $table->string('platform_sku')->nullable();
    $table->string('sync_status')->default('pending'); // pending, synced, error, out_of_sync
    $table->string('sync_direction')->default('bidirectional');
    $table->timestamp('last_synced_at')->nullable();
    $table->timestamp('last_sync_attempt_at')->nullable();
    $table->text('last_sync_error')->nullable();
    $table->json('platform_data')->nullable();
    $table->json('sync_metadata')->nullable();
    $table->boolean('auto_sync')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->unique(['product_id', 'shop_id', 'platform']);
});
```

---

## SKU Management

### SKU Requirements
- **Unique**: Each SKU must be unique across the entire system
- **Shared**: Same SKU used across all e-commerce platforms
- **Auto-generation**: If no SKU provided during import, one is generated

### SKU Format
Generated SKUs follow this pattern:
```
{PREFIX}-{YYMMDD}-{RANDOM}
```

**Examples:**
- `PRD-260213-AB12` (Default prefix)
- `WC-260213-CD34` (WooCommerce import)
- `SH-260213-EF56` (Shopify import)

### Generate SKU

```php
use App\Models\Product;

// Default prefix (PRD)
$sku = Product::generateSku();

// Custom prefix
$sku = Product::generateSku('CUSTOM');

// Example: PRD-260213-XY89
```

---

## Synchronization Flow

### FROM Platform (Import)

Fetches products from e-commerce platforms and creates/updates local products:

```
E-commerce Platform → Local Database
```

**Process:**
1. Fetch products from platform API
2. Check if product exists locally by SKU
3. If not exists, create new product
4. If exists, update product details
5. Create/update sync record
6. Store platform-specific data (images, variants, etc.)

### TO Platform (Export)

Pushes local products to e-commerce platforms:

```
Local Database → E-commerce Platform
```

**Process:**
1. Check if product already synced (has platform_product_id)
2. If synced, update existing product on platform
3. If not synced, create new product on platform
4. Create/update sync record with platform response
5. Store platform product ID for future updates

### Bidirectional Sync

Both import and export operations:

```
Local Database ↔ E-commerce Platform
```

---

## Name Aliasing

Products can have different names on each platform:

| Platform | Name | SKU |
|----------|------|-----|
| **Local System** | "Red T-Shirt Large" | PRD-001 |
| **WooCommerce** | "Premium Red Tee - Size L" | PRD-001 |
| **Shopify** | "Red Cotton T-Shirt (L)" | PRD-001 |

The `platform_name` field in `product_ecommerce_sync` stores the alias.

### Setting Platform Names

When syncing TO platform:

```php
use App\Actions\Product\SyncProductToEcommerce;

$syncAction = app(SyncProductToEcommerce::class);

$result = $syncAction->execute($product, $shop, 'woocommerce', [
    'platform_name' => 'Premium Red Tee - Size L',
]);
```

---

## Usage Examples

### 1. Sync FROM Platform (Import Products)

#### Via Command Line

```bash
# Sync from all enabled platforms
php artisan ecommerce:sync-products {shop-id} --direction=from-platform

# Sync from specific platform
php artisan ecommerce:sync-products {shop-id} --platform=woocommerce --direction=from-platform

# Limit number of products
php artisan ecommerce:sync-products {shop-id} --platform=shopify --limit=50
```

#### Via Code

```php
use App\Actions\Product\SyncProductFromEcommerce;
use App\Models\Shop;

$shop = Shop::find(1);
$syncAction = app(SyncProductFromEcommerce::class);

// Sync from WooCommerce
$result = $syncAction->execute($shop, 'woocommerce', [
    'per_page' => 100,
    'page' => 1,
]);

// Sync from all platforms
$results = $syncAction->executeFromAllPlatforms($shop);
```

### 2. Sync TO Platform (Export Products)

#### Via Code

```php
use App\Actions\Product\SyncProductToEcommerce;
use App\Models\{Product, Shop};

$product = Product::where('sku', 'PRD-001')->first();
$shop = Shop::find(1);
$syncAction = app(SyncProductToEcommerce::class);

// Sync to WooCommerce with custom name
$result = $syncAction->execute($product, $shop, 'woocommerce', [
    'platform_name' => 'Custom Product Name for WooCommerce',
]);

// Sync to all platforms
$results = $syncAction->executeToAllPlatforms($product, $shop);
```

### 3. Sync Inventory Only

```php
use App\Actions\Product\SyncProductToEcommerce;

$syncAction = app(SyncProductToEcommerce::class);

// Update only stock quantity on platform
$result = $syncAction->syncInventory($product, $shop, 'shopify');
```

### 4. Check Sync Status

```php
use App\Models\Product;

$product = Product::find(1);

// Get sync record
$sync = $product->getSyncRecord(shopId: 1, platform: 'woocommerce');

// Check if synced
if ($product->isSyncedWith(shopId: 1, platform: 'shopify')) {
    echo "Product is synced with Shopify";
}

// Get all synced platforms
$platforms = $product->getSyncedPlatforms();
// Returns: ['woocommerce', 'shopify']
```

### 5. Working with Sync Records

```php
use App\Models\ProductEcommerceSync;

// Find sync records needing sync
$pending = ProductEcommerceSync::needsSync()->get();

// Find by platform
$wooSyncs = ProductEcommerceSync::platform('woocommerce')->get();

// Find by status
$errors = ProductEcommerceSync::status('error')->get();

// Mark as synced
$sync->markSynced(['price' => 29.99]);

// Mark as failed
$sync->markFailed('API connection timeout');

// Add metadata
$sync->addSyncMetadata('inventory_updated', [
    'old_quantity' => 10,
    'new_quantity' => 15,
]);
```

---

## Model Relationships

### Product Model

```php
// Get all sync records
$product->ecommerceSyncs;

// Generate SKU
$sku = Product::generateSku();
$sku = Product::generateSku('CUSTOM');

// Get sync record for specific platform
$sync = $product->getSyncRecord(1, 'woocommerce');

// Check if synced
$isSynced = $product->isSyncedWith(1, 'shopify');

// Get synced platforms
$platforms = $product->getSyncedPlatforms();
```

### Shop Model

```php
// Get all product syncs for this shop
$shop->ecommerceSyncs;
```

### ProductEcommerceSync Model

```php
// Relationships
$sync->product;
$sync->shop;

// Status checks
$sync->isPending();
$sync->isSynced();
$sync->hasError();
$sync->isOutOfSync();

// Status updates
$sync->markSynced();
$sync->markFailed('error message');
$sync->markOutOfSync();

// Metadata
$sync->addSyncMetadata('action', ['data' => 'value']);

// Scopes
ProductEcommerceSync::platform('woocommerce')->get();
ProductEcommerceSync::status('synced')->get();
ProductEcommerceSync::autoSync()->get();
ProductEcommerceSync::needsSync()->get();
```

---

## Services

### WooCommerceProductSyncService

```php
use App\Services\Integration\WooCommerceProductSyncService;

$service = app(WooCommerceProductSyncService::class);

// Import from WooCommerce
$result = $service->syncFromPlatform($shop, [
    'per_page' => 100,
    'page' => 1,
]);

// Export to WooCommerce
$result = $service->syncToPlatform($shop, $product, [
    'platform_name' => 'Custom Name',
]);

// Sync inventory only
$result = $service->syncInventory($shop, $product);
```

### ShopifyProductSyncService

```php
use App\Services\Integration\ShopifyProductSyncService;

$service = app(ShopifyProductSyncService::class);

// Import from Shopify
$result = $service->syncFromPlatform($shop, [
    'limit' => 250,
    'since_id' => 12345, // Optional: fetch products after this ID
]);

// Export to Shopify
$result = $service->syncToPlatform($shop, $product, [
    'platform_name' => 'Custom Name',
    'vendor' => 'My Brand',
    'product_type' => 'Apparel',
]);

// Sync inventory only
$result = $service->syncInventory($shop, $product);
```

---

## Artisan Commands

### ecommerce:sync-products

Synchronize products between local database and e-commerce platforms.

**Signature:**
```bash
php artisan ecommerce:sync-products {shop} 
                                    {--platform=} 
                                    {--direction=both} 
                                    {--limit=100}
```

**Arguments:**
- `shop` - Shop ID or UUID

**Options:**
- `--platform` - Platform to sync: `woocommerce`, `shopify`, or `all` (default: all)
- `--direction` - Sync direction: `from-platform`, `to-platform`, or `both` (default: both)
- `--limit` - Number of products to fetch from platform (default: 100)

**Examples:**
```bash
# Sync all platforms, both directions
php artisan ecommerce:sync-products 1

# Import from WooCommerce only
php artisan ecommerce:sync-products 1 --platform=woocommerce --direction=from-platform

# Import from Shopify with limit
php artisan ecommerce:sync-products 1 --platform=shopify --direction=from-platform --limit=50

# Using shop UUID
php artisan ecommerce:sync-products 550e8400-e29b-41d4-a716-446655440000
```

---

## Platform-Specific Details

### WooCommerce

**API Endpoints Used:**
- `GET /wp-json/wc/v3/products` - Fetch products
- `POST /wp-json/wc/v3/products` - Create product
- `PUT /wp-json/wc/v3/products/{id}` - Update product

**Import Mapping:**
```php
Local Field          → WooCommerce Field
─────────────────────────────────────────
name                 → name
description          → description (stripped HTML)
sku                  → sku
cost_price           → regular_price
selling_price        → price
stock_quantity       → stock_quantity
track_stock          → manage_stock
status               → status (publish/draft)
```

**Platform Data Stored:**
```json
{
  "price": "29.99",
  "regular_price": "39.99",
  "sale_price": "29.99",
  "stock_quantity": 100,
  "manage_stock": true,
  "permalink": "https://store.com/product/...",
  "images": [...]
}
```

### Shopify

**API Endpoints Used:**
- `GET /admin/api/2024-01/products.json` - Fetch products
- `POST /admin/api/2024-01/products.json` - Create product
- `PUT /admin/api/2024-01/variants/{id}.json` - Update variant
- `POST /admin/api/2024-01/inventory_levels/set.json` - Update inventory

**Import Mapping:**
```php
Local Field          → Shopify Field
────────────────────────────────────────
name                 → title (+ variant.title)
description          → body_html (stripped HTML)
sku                  → variant.sku
cost_price           → variant.compare_at_price
selling_price        → variant.price
stock_quantity       → variant.inventory_quantity
track_stock          → variant.inventory_management
status               → status (active/draft)
```

**Platform Data Stored:**
```json
{
  "product_id": 123456789,
  "variant_id": 987654321,
  "price": "29.99",
  "compare_at_price": "39.99",
  "inventory_quantity": 100,
  "inventory_management": "shopify",
  "handle": "product-slug",
  "images": [...]
}
```

**Note:** Shopify creates each product variant as a separate sync record since variants have unique IDs and inventory tracking.

---

## Error Handling

### Common Errors

1. **Authentication Failed**
   - Check API credentials are correct and encrypted
   - Verify shop integration is enabled

2. **Product Not Found on Platform**
   - Occurs when trying to update non-existent product
   - Sync record will be marked as `error` status

3. **SKU Conflict**
   - Local SKU must be unique
   - Platform may reject duplicate SKUs

4. **Rate Limiting**
   - WooCommerce: Typically unlimited for self-hosted
   - Shopify: 2 requests/second default

### Logging

All sync operations are logged:

```php
// Success
Log::info('Product synced to e-commerce platform', [
    'product_id' => $product->id,
    'shop_id' => $shop->id,
    'platform' => 'woocommerce',
]);

// Error
Log::error('Failed to sync product', [
    'product_id' => $product->id,
    'error' => $exception->getMessage(),
]);
```

### Retry Failed Syncs

```php
use App\Models\ProductEcommerceSync;
use App\Actions\Product\SyncProductToEcommerce;

$syncAction = app(SyncProductToEcommerce::class);

// Get all failed syncs
$failedSyncs = ProductEcommerceSync::status('error')
    ->where('auto_sync', true)
    ->get();

foreach ($failedSyncs as $sync) {
    $result = $syncAction->execute(
        $sync->product,
        $sync->shop,
        $sync->platform
    );
    
    if ($result['success']) {
        echo "Retry successful for product {$sync->product_id}\n";
    }
}
```

---

## Scheduling Auto-Sync

Add to `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Sync products from all platforms every hour
Schedule::command('ecommerce:sync-products {shop-id} --direction=from-platform')
    ->hourly();

// Sync inventory every 30 minutes
Schedule::call(function () {
    $shops = Shop::whereHas('ecommerceSyncs')->get();
    
    foreach ($shops as $shop) {
        // Sync inventory for products that changed
        // Implementation depends on change tracking
    }
})->everyThirtyMinutes();
```

---

## Testing

### Test Import from Platform

```bash
# Test WooCommerce import
php artisan ecommerce:sync-products 1 --platform=woocommerce --direction=from-platform --limit=5

# Check results
php artisan tinker
>>> \App\Models\ProductEcommerceSync::platform('woocommerce')->count();
>>> \App\Models\ProductEcommerceSync::platform('woocommerce')->latest()->first();
```

### Test Export to Platform

```php
// In tinker or test script
$product = \App\Models\Product::first();
$shop = \App\Models\Shop::first();

$syncAction = app(\App\Actions\Product\SyncProductToEcommerce::class);
$result = $syncAction->execute($product, $shop, 'woocommerce');

dd($result);
```

---

## Future Enhancements

### Phase 3 (Not Yet Implemented)

- [ ] **Webhook Listeners**: Real-time updates from platforms
- [ ] **Product Category Mapping**: Map local categories to platform categories
- [ ] **Image Sync**: Sync product images between platforms
- [ ] **Variant Sync**: Full support for product variations
- [ ] **Price Rules**: Platform-specific pricing strategies
- [ ] **Bulk Operations UI**: Admin interface for bulk sync
- [ ] **Sync Dashboard**: Visual sync status and analytics
- [ ] **Conflict Resolution**: Handle concurrent updates
- [ ] **Custom Field Mapping**: Map additional product attributes
- [ ] **Multi-location Inventory**: Sync stock across locations

---

## Troubleshooting

### Products Not Syncing

1. Check shop integration is enabled:
   ```php
   $shop->getEnabledIntegrations();
   ```

2. Verify API credentials:
   ```php
   $config = $shop->getIntegrationConfig('woocommerce');
   // Check config values
   ```

3. Check sync status:
   ```php
   $sync = $product->getSyncRecord($shopId, 'woocommerce');
   echo $sync->sync_status;
   echo $sync->last_sync_error;
   ```

4. Review logs:
   ```bash
   tail -f storage/logs/laravel.log | grep -i sync
   ```

### Duplicate Products Created

- Ensure SKUs are unique before importing
- Check if products exist before creating:
  ```php
  $existing = Product::where('sku', $sku)->first();
  ```

### Inventory Not Updating

- Verify `track_stock` is enabled on product
- Check platform inventory management settings
- For Shopify, ensure location is configured

---

## Security Considerations

- ✅ All API credentials encrypted at rest using Laravel Crypt
- ✅ API calls use HTTPS only
- ✅ Rate limiting respected for each platform
- ✅ Validation on all input data
- ✅ Soft deletes for sync records (audit trail)
- ✅ Comprehensive logging for security audits

---

## Summary

The Product E-commerce Synchronization system provides a robust, flexible way to manage products across multiple platforms while maintaining data integrity through unique SKU management and platform-specific aliasing. Both manual and automated synchronization options are available, with comprehensive error handling and logging for production reliability.
