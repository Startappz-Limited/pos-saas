# E-Commerce Platform Integration

## Overview

This document outlines the e-commerce platform integration system implemented for shops in the fitness center management system. The system allows shops to connect with external e-commerce platforms (WooCommerce and Shopify) for future product and order synchronization.

**Last Updated:** February 13, 2026  
**Status:** Phase 1 Complete - Connection setup and credential management  
**Next Phase:** Product and order synchronization (planned)

---

## Architecture

### Design Pattern

The integration follows the established Service-Action pattern:

```
User Input (View)
    ↓
Form Request Validation
    ↓
Controller
    ↓
Service Layer (Integration Services)
    ↓
Action Layer (CreateShopAction/UpdateShopAction)
    ↓
Model (Shop with encrypted settings)
```

### Key Components

1. **Services** (`app/Services/Integration/`)
   - `WooCommerceService.php` - WooCommerce API connection handling
   - `ShopifyService.php` - Shopify API connection handling

2. **Actions** (`app/Actions/Shop/`)
   - `CreateShopAction.php` - Creates shops with encrypted integration config
   - `UpdateShopAction.php` - Updates shops while preserving existing integrations

3. **Model** (`app/Models/Shop.php`)
   - Integration helper methods
   - JSON settings column for flexible configuration storage

4. **Validation** (`app/Http/Requests/`)
   - `StoreShopRequest.php` - Validation for shop creation with integrations
   - `UpdateShopRequest.php` - Validation for shop updates with integrations

5. **Views** (`resources/views/shops/`)
   - `create.blade.php` - Shop creation form with integration tabs
   - `edit.blade.php` - Shop editing form with existing integration data

---

## Phase 1: Connection Setup (IMPLEMENTED)

### Features Completed

✅ **Credential Storage**
- Encrypted API credentials using Laravel's `Crypt` facade
- Stored in Shop model's `settings` JSON column
- Structure: `settings.integrations.{platform}.*`

✅ **Connection Testing**
- Real-time AJAX connection validation
- Tests API credentials before saving
- User-friendly feedback with connection status

✅ **Platform Support**
- **WooCommerce**: Store URL, Consumer Key, Consumer Secret
- **Shopify**: Shop Domain, Access Token

✅ **UI/UX**
- Tabbed interface for multiple platforms
- Enable/disable toggles per platform
- "Test Connection" buttons with loading states
- Visual connection status indicators
- Timestamp of last successful test

✅ **Security**
- All API credentials encrypted at rest
- Credentials masked in edit forms
- CSRF protection on all endpoints
- Permission-based access control

---

## Phase 2: Data Synchronization (PLANNED)

### Outstanding Questions

**🔴 CRITICAL DECISION REQUIRED:** What data should synchronize between the fitness center system and external e-commerce platforms?

#### Option A: Products/Inventory Only
- Sync product catalog and stock levels
- One-way or two-way inventory updates
- Price synchronization
- SKU mapping

**Pros:** Simpler, focused, less complex error handling  
**Cons:** Limited business value without order processing  
**Effort:** Medium (3-4 weeks)

#### Option B: Products + Orders
- Everything from Option A
- Incoming order creation in fitness center system
- Order status updates
- Customer creation/matching

**Pros:** More complete solution, better business value  
**Cons:** More complex, requires customer management consideration  
**Effort:** High (6-8 weeks)

#### Option C: Full Synchronization
- Products, Orders, Customers
- Payment reconciliation
- Return/refund processing
- Two-way data flow

**Pros:** Complete integration, maximum business value  
**Cons:** Very complex, extensive error handling, data conflict resolution  
**Effort:** Very High (10-12 weeks)

### Technical Considerations for Phase 2

When implementing data synchronization, consider:

1. **Queue Jobs** - Long-running sync operations should be queued
   - `Jobs/Integration/SyncProductsJob.php`
   - `Jobs/Integration/SyncOrdersJob.php`
   - `Jobs/Integration/ProcessWebhookJob.php`

2. **Actions** - Atomic sync operations
   - `Actions/Integration/SyncProductAction.php`
   - `Actions/Integration/CreateOrderFromExternalAction.php`
   - `Actions/Integration/UpdateStockLevelAction.php`

3. **Database Schema** - Consider adding:
   - `shop_integration_logs` table for audit trail
   - `external_product_mappings` table for SKU matching
   - `external_orders` table for order tracking
   - Foreign key columns on products/orders for external IDs

4. **Webhooks** - Real-time updates from external platforms
   - Routes in `routes/api.php`
   - Webhook signature verification
   - Idempotency handling

5. **Error Handling**
   - Failed sync retry mechanism
   - Conflict resolution strategies
   - Admin notification system
   - Detailed logging

6. **Rate Limiting**
   - Respect API rate limits
   - Implement exponential backoff
   - Queue throttling

---

## Database Schema

### Shop Model - Settings Column

```json
{
  "integrations": {
    "woocommerce": {
      "enabled": true,
      "store_url": "https://mystore.com",
      "consumer_key": "encrypted_ck_xxxx",
      "consumer_secret": "encrypted_cs_xxxx",
      "last_tested_at": "2026-02-13T10:30:00Z"
    },
    "shopify": {
      "enabled": false,
      "shop_domain": "mystore.myshopify.com",
      "access_token": "encrypted_shpat_xxxx",
      "last_tested_at": null
    }
  }
}
```

---

## API Endpoints

### Connection Testing

**POST** `/shops/test-integration`

**Request:**
```json
{
  "platform": "woocommerce",
  "credentials": {
    "store_url": "https://mystore.com",
    "consumer_key": "ck_xxxx",
    "consumer_secret": "cs_xxxx"
  }
}
```

**Response (Success):**
```json
{
  "connected": true,
  "message": "Successfully connected to WooCommerce",
  "details": {
    "store_name": "My Fitness Store",
    "version": "8.5.2",
    "currency": "USD"
  }
}
```

**Response (Failure):**
```json
{
  "connected": false,
  "message": "Failed to connect: 401",
  "details": {
    "status_code": 401
  }
}
```

---

## Shop Model Methods

### Integration Helper Methods

```php
// Get integration configuration
$config = $shop->getIntegrationConfig('woocommerce');

// Set integration configuration
$shop->setIntegrationConfig('woocommerce', $config);
$shop->save();

// Check if integration is connected
if ($shop->isIntegrationConnected('woocommerce')) {
    // Integration is enabled and has credentials
}

// Get connection status
$status = $shop->getConnectionStatus('shopify');
// Returns: ['connected' => bool, 'enabled' => bool, 'last_tested_at' => string]

// Get all enabled integrations
$platforms = $shop->getEnabledIntegrations();
// Returns: ['woocommerce', 'shopify']
```

---

## Service Methods

### WooCommerceService

```php
// Test connection with plain credentials
$result = $wooCommerceService->testConnection([
    'store_url' => 'https://mystore.com',
    'consumer_key' => 'ck_xxxx',
    'consumer_secret' => 'cs_xxxx',
]);

// Test connection with encrypted credentials
$result = $wooCommerceService->testConnectionWithEncrypted($encryptedConfig);

// Encrypt credentials for storage
$encrypted = $wooCommerceService->encryptCredentials($plainConfig);
```

### ShopifyService

```php
// Test connection with plain credentials
$result = $shopifyService->testConnection([
    'shop_domain' => 'mystore.myshopify.com',
    'access_token' => 'shpat_xxxx',
]);

// Test connection with encrypted credentials
$result = $shopifyService->testConnectionWithEncrypted($encryptedConfig);

// Encrypt credentials for storage
$encrypted = $shopifyService->encryptCredentials($plainConfig);
```

---

## Security Considerations

### Encryption

- All API credentials are encrypted using Laravel's `Crypt::encrypt()`
- Encryption key stored in `.env` file (`APP_KEY`)
- Never expose encrypted credentials in responses
- Mask credentials in edit forms (show `••••••••••••••••`)

### Validation

- URL validation for store URLs
- String length limits on all credential fields
- CSRF token required for connection testing
- Permission checks on all shop-related actions

### Best Practices

1. **Never log credentials** - Use Laravel's exception handler to filter sensitive data
2. **Use HTTPS only** - Enforce SSL for all external API calls
3. **Rotate credentials regularly** - Encourage users to refresh tokens periodically
4. **Monitor failed attempts** - Log integration failures for security analysis
5. **Principle of least privilege** - Request minimum required API scopes

---

## Testing

### Test Coverage

All integration functionality should be tested:

1. **Feature Tests**
   - Shop creation with integration config
   - Shop update with integration config
   - Connection testing endpoint
   - Validation of integration fields

2. **Unit Tests**
   - Service connection methods (with HTTP mocking)
   - Encryption/decryption of credentials
   - Shop model integration methods

3. **Browser Tests** (Future)
   - End-to-end user flow of adding integration
   - Real-time connection testing in UI

### Running Tests

```bash
# Run all integration-related tests
php artisan test --filter=Integration --compact

# Run specific test file
php artisan test tests/Feature/Shop/CreateShopWithIntegrationTest.php --compact

# Run with coverage
php artisan test --coverage --min=80
```

---

## Configuration

### Environment Variables

No additional environment variables required for Phase 1.

For Phase 2, consider adding:

```env
# Rate limiting
WOOCOMMERCE_RATE_LIMIT=60
SHOPIFY_RATE_LIMIT=40

# Webhook secrets
WOOCOMMERCE_WEBHOOK_SECRET=xxx
SHOPIFY_WEBHOOK_SECRET=xxx

# Feature flags
SYNC_PRODUCTS_ENABLED=true
SYNC_ORDERS_ENABLED=false
```

---

## Troubleshooting

### Common Issues

**Issue:** "Connection error: SSL certificate problem"  
**Solution:** Ensure the external platform has valid SSL certificate. For local testing, you may need to disable SSL verification (not recommended for production).

**Issue:** "Failed to connect: 401"  
**Solution:** Verify API credentials are correct. For WooCommerce, ensure REST API is enabled. For Shopify, verify the access token has required scopes.

**Issue:** "Decryption or connection error"  
**Solution:** Check that APP_KEY hasn't changed. If APP_KEY is rotated, all encrypted data must be re-encrypted.

**Issue:** Connection test succeeds but form submission fails  
**Solution:** Check validation rules. Ensure field names match between form and validation rules.

### Debug Tips

1. **Enable query logging** - See database interactions
2. **Check Laravel logs** - `storage/logs/laravel.log`
3. **Use Laravel Debugbar** - Inspect requests and responses
4. **Test credentials externally** - Use Postman/cURL to verify API access

---

## Future Enhancements

### Phase 3 and Beyond

- [ ] Support for additional platforms (BigCommerce, Magento, custom APIs)
- [ ] Automated sync scheduling with configurable intervals
- [ ] Conflict resolution UI for duplicate products
- [ ] Integration analytics dashboard
- [ ] Bulk product import/export
- [ ] Multi-currency support in sync
- [ ] Integration marketplace for plugin extensions

---

## References

### External Documentation

- [WooCommerce REST API Documentation](https://woocommerce.github.io/woocommerce-rest-api-docs/)
- [Shopify Admin API Documentation](https://shopify.dev/docs/api/admin-rest)
- [Laravel Encryption Documentation](https://laravel.com/docs/12.x/encryption)
- [Laravel HTTP Client Documentation](https://laravel.com/docs/12.x/http-client)

### Internal Documentation

- [Shop Management System](../system/shop-management.md)
- [Product Management System](../system/product-management.md)
- [API Development Guidelines](../.ai/general/0.7 api_guide.md)
- [Coding Standards](../.ai/general/0.9 coding_standards_guide.md)

---

## Change Log

### 2026-02-13 - Phase 1 Implementation
- ✅ Created WooCommerce and Shopify integration services
- ✅ Updated Shop model with integration helper methods
- ✅ Extended validation for integration fields
- ✅ Updated CreateShopAction and UpdateShopAction
- ✅ Added connection testing endpoint
- ✅ Updated create and edit views with integration UI
- ✅ Implemented encrypted credential storage
- ✅ Added real-time AJAX connection testing

### Future Releases
- Phase 2: Data synchronization (TBD based on business requirements)
- Phase 3: Advanced features and additional platform support

---

## Support

For questions or issues related to e-commerce integration:

1. Check this documentation first
2. Review Laravel Boost guidelines in `.github/copilot-instructions.md`
3. Check the test suite for usage examples
4. Consult external platform documentation
5. Contact the development team

---

**Document Version:** 1.0  
**Author:** Fitness Center Development Team  
**Date:** February 13, 2026
