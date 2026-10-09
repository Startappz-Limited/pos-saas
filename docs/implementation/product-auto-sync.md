# Product Auto-Sync to E-commerce Platforms

## Overview

Products are now **automatically synchronized** to linked e-commerce platforms (WooCommerce/Shopify) whenever they are created or updated locally. This eliminates the need for manual sync operations for individual product changes.

## How It Works

### Automatic Triggers

The system automatically syncs products in the following scenarios:

1. **New Product Created**
   - When you create a new product locally, it's automatically pushed to all linked e-commerce shops
   - Happens immediately after product creation

2. **Product Updated**
   - When you edit any product details (name, price, stock, etc.), changes are automatically synced
   - Happens immediately after product update

### Sync Process

1. **ProductObserver** listens for product `created` and `updated` events
2. Finds all shops with active e-commerce integrations
3. For each shop, checks which platforms are enabled (WooCommerce, Shopify, or both)
4. Calls `SyncProductToEcommerce` action to push changes to each platform
5. Logs success/failure for each sync operation

## Loop Prevention

The system includes built-in protection against infinite sync loops:

- When importing products **from** platforms (WooCommerce/Shopify → Local), auto-sync is temporarily disabled
- A static flag `ProductObserver::$syncInProgress` prevents re-syncing during imports
- This ensures products being created/updated during import don't trigger a sync back to the platform

## Configuration

### Enable/Disable Auto-Sync

Auto-sync is **enabled by default** for all products. To have products sync automatically:

1. **Shop must have e-commerce integration enabled**
   - Go to Shop → Edit
   - Enable WooCommerce or Shopify integration
   - Enter valid API credentials

2. **Products are synced only to enabled platforms**
   - If shop has WooCommerce enabled, products sync to WooCommerce
   - If shop has Shopify enabled, products sync to Shopify
   - If both enabled, products sync to both platforms

### Multiple Shops

If you have multiple shops with e-commerce integrations:
- Products are synced to **all** linked shops automatically
- Each shop can have different platforms enabled
- Sync runs independently for each shop/platform combination

## Monitoring

### Check Logs

All auto-sync operations are logged:

```bash
# Watch auto-sync in real-time
tail -f storage/logs/laravel.log | grep "Auto-sync"

# Check auto-sync history
grep "Auto-syncing product" storage/logs/laravel.log

# Check for auto-sync errors
grep "Failed to auto-sync" storage/logs/laravel.log
```

### Log Entries

**Successful Sync:**
```
[2026-02-13 10:30:45] local.INFO: Auto-syncing product to e-commerce platforms  
{"product_id":123,"product_name":"Running Shoes","action":"created","shops_count":2}

[2026-02-13 10:30:46] local.INFO: Product auto-synced to WooCommerce  
{"product_id":123,"shop_id":11}

[2026-02-13 10:30:47] local.INFO: Product auto-synced to Shopify  
{"product_id":123,"shop_id":12}
```

**Failed Sync:**
```
[2026-02-13 10:30:48] local.ERROR: Failed to auto-sync product to WooCommerce  
{"product_id":123,"shop_id":11,"error":"API authentication failed"}
```

## Behavior Details

### What Gets Synced

When a product is auto-synced, the following data is pushed:

**To WooCommerce:**
- Product name
- SKU
- Regular price (from selling_price)
- Description
- Stock quantity
- Stock tracking status
- Product status (active → publish, inactive → draft)

**To Shopify:**
- Product title
- SKU
- Price (from selling_price)
- Description (body_html)
- Inventory quantity
- Inventory tracking
- Product status (active/inactive)

### What Doesn't Trigger Auto-Sync

The following operations **do NOT** trigger automatic sync:

1. **Importing from platforms** (prevents infinite loops)
2. **Product deletion** (not currently synced)
3. **Stock adjustments via inventory system** (considered separate operation)
4. **Bulk imports** (uses manual sync workflow)

## Manual Sync vs Auto-Sync

### When to Use Manual Sync

Use the manual sync feature (Products → Sync Products button) when:

1. **Initial import** - Importing all products from WooCommerce/Shopify
2. **Bulk updates** - Syncing many products at once
3. **Re-sync** - Fixing sync issues or pulling latest data from platform
4. **Direction control** - Choosing to sync FROM platform, TO platform, or both

### When Auto-Sync Happens

Auto-sync happens automatically:

1. Creating a single product locally
2. Editing a single product locally
3. Real-time changes that need immediate sync

## Performance Considerations

### Sync Speed

- Auto-sync is **synchronous** (happens immediately in the request)
- For single products, this is typically fast (< 2 seconds per platform)
- Multiple shops/platforms will add latency proportionally

### Recommendations

1. **For frequent edits**: Auto-sync ensures data consistency without extra steps
2. **For bulk operations**: Use manual sync with queue system for better performance
3. **For large catalogs**: Consider disabling auto-sync during bulk edits, then run manual sync

## Error Handling

### Graceful Failures

If auto-sync fails:
- Error is logged but doesn't block the product save operation
- Product is successfully saved locally regardless of sync status
- You retain the product data even if platform is temporarily unavailable

### Common Errors

**API Authentication Failed:**
- Cause: Invalid or expired API credentials
- Solution: Update shop's e-commerce integration credentials

**HTTP Timeout:**
- Cause: Platform API slow or unreachable
- Solution: Check platform status, retry with manual sync

**Product Already Exists:**
- Cause: Product with same SKU exists on platform
- Solution: System updates existing product instead of creating duplicate

## Disabling Auto-Sync

If you need to temporarily disable auto-sync:

### Option 1: Disable Shop Integration

1. Go to Shop → Edit
2. Uncheck "Enable WooCommerce/Shopify"
3. Products won't auto-sync to that shop

### Option 2: Programmatic Disable (Advanced)

For specific operations where you don't want auto-sync:

```php
use App\Observers\ProductObserver;

// Disable auto-sync
ProductObserver::$syncInProgress = true;

// Your product operations
$product->update(['name' => 'New Name']);

// Re-enable auto-sync
ProductObserver::$syncInProgress = false;
```

## Testing

### Verify Auto-Sync Works

1. **Create a Test Product:**
   ```
   - Go to Products → Add Product
   - Fill in details (name, SKU, price)
   - Save
   - Check logs: grep "Auto-syncing product" storage/logs/laravel.log
   - Verify product appears on WooCommerce/Shopify
   ```

2. **Update a Product:**
   ```
   - Edit existing product
   - Change price or name
   - Save
   - Check logs for auto-sync entry
   - Verify changes appear on platform
   ```

3. **Import Products:**
   ```
   - Use Products → Sync Products → From Platform
   - Verify imported products don't trigger auto-sync back
   - Check logs: should see "from_platform" sync but no auto-sync
   ```

## Architecture

### Files Involved

- **Observer**: [app/Observers/ProductObserver.php](app/Observers/ProductObserver.php)
  - Listens to product events
  - Triggers auto-sync logic
  
- **Provider**: [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php#L43)
  - Registers ProductObserver
  
- **Action**: [app/Actions/Product/SyncProductToEcommerce.php](app/Actions/Product/SyncProductToEcommerce.php)
  - Handles actual sync to platform
  
- **Services**:
  - [app/Services/Integration/WooCommerceProductSyncService.php](app/Services/Integration/WooCommerceProductSyncService.php)
  - [app/Services/Integration/ShopifyProductSyncService.php](app/Services/Integration/ShopifyProductSyncService.php)

### Flow Diagram

```
Product Created/Updated
        ↓
ProductObserver (created/updated)
        ↓
Check: syncInProgress flag
        ↓ (if false)
Find shops with integrations
        ↓
For each shop/platform:
        ↓
SyncProductToEcommerce::execute()
        ↓
WooCommerceProductSyncService or ShopifyProductSyncService
        ↓
HTTP API Request to Platform
        ↓
Update ProductEcommerceSync record
        ↓
Log success/failure
```

## Comparison: Manual Sync vs Auto-Sync

| Feature | Manual Sync | Auto-Sync |
|---------|-------------|-----------|
| **Trigger** | User clicks button | Automatic on create/update |
| **Direction** | FROM, TO, or BOTH | TO platform only |
| **Processing** | Queued (async) | Synchronous (immediate) |
| **Bulk Support** | Yes (up to 1000 products) | No (single product) |
| **Image Download** | Yes (from platform) | No (local images only) |
| **Use Case** | Initial import, bulk sync | Real-time updates |
| **Performance** | Handles large datasets | Best for single products |

## FAQ

**Q: Will auto-sync slow down product editing?**
A: For single products with one shop/platform, the delay is minimal (1-2 seconds). With multiple shops/platforms, there will be proportional delay.

**Q: What if the platform API is down?**
A: The product saves successfully locally. The sync error is logged, and you can manually sync later.

**Q: Can I sync changes FROM the platform automatically?**
A: No, auto-sync only pushes changes TO platforms. Use manual sync to pull changes FROM platforms.

**Q: What happens if I have 5 shops with integrations?**
A: The product will be synced to all 5 shops automatically. Consider the performance impact.

**Q: Can I choose which shops to auto-sync to?**
A: Currently, all shops with enabled integrations are synced. To exclude a shop, disable its integration temporarily.

**Q: Does this work with the queue system?**
A: No, auto-sync is synchronous. The queue system is only for bulk manual sync operations.

## Troubleshooting

### Product Not Appearing on Platform

1. **Check logs**: `grep "product_id:<id>" storage/logs/laravel.log`
2. **Verify shop integration**: Shop → Edit → Check WooCommerce/Shopify enabled
3. **Check API credentials**: Test connection from shop page
4. **Try manual sync**: Use Products → Sync Products to re-sync

### Auto-Sync Taking Too Long

1. **Check number of shops**: Multiple shops = multiple sync operations
2. **Test platform speed**: Check if WooCommerce/Shopify API is responsive
3. **Consider disabling for bulk edits**: Disable integration during bulk operations

### Products Being Synced in Circles

1. **Check loop prevention**: Verify `ProductObserver::$syncInProgress` is working
2. **Review logs**: Look for "from_platform" and "to_platform" sync patterns
3. **Update code**: Ensure latest version with loop prevention

## Related Documentation

- [Product Sync Queue](product-sync-queue.md) - Bulk sync with queue system
- [E-commerce Integration](../system/ecommerce-integration.md) - Setting up integrations
