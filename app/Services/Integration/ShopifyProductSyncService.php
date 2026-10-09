<?php

namespace App\Services\Integration;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\Shop;
use App\Observers\ProductObserver;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopifyProductSyncService
{
    public function __construct(
        protected ImageDownloadService $imageDownloadService
    ) {}

    /**
     * Fetch product list from Shopify API without processing.
     *
     * @return array{success: bool, products?: array, message?: string}
     */
    public function fetchProductsFromPlatform(Shop $shop, ?array $options = []): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            $limit = $options['limit'] ?? 250;
            $sinceId = $options['since_id'] ?? null;

            $params = ['limit' => $limit];
            if ($sinceId) {
                $params['since_id'] = $sinceId;
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->get("https://{$shopDomain}/admin/api/2024-01/products.json", $params);

            if (! $response->successful()) {
                $statusCode = $response->status();
                $errorBody = $response->json() ?? ['message' => $response->body()];

                Log::error('Shopify API request failed', [
                    'shop_id' => $shop->id,
                    'shop_domain' => $shopDomain,
                    'status_code' => $statusCode,
                    'error' => $errorBody,
                ]);

                $errorMessage = $errorBody['errors'] ?? $errorBody['error'] ?? $errorBody['message'] ?? 'Unknown error';
                if (is_array($errorMessage)) {
                    $errorMessage = json_encode($errorMessage);
                }

                // Provide helpful message for common errors
                if ($statusCode === 401) {
                    $errorMessage = 'Authentication failed: Invalid access token. Please verify your Shopify API credentials.';
                } elseif ($statusCode === 403) {
                    $errorMessage = 'Permission denied: The access token does not have permission to read products. Please check your API scopes.';
                } elseif ($statusCode === 404) {
                    $errorMessage = 'Shop not found: Please verify the shop domain is correct (e.g., your-shop.myshopify.com).';
                } elseif ($statusCode === 429) {
                    $errorMessage = 'Rate limit exceeded: Too many requests. Please wait a moment and try again.';
                }

                return [
                    'success' => false,
                    'message' => "Failed to fetch products from Shopify (HTTP {$statusCode}): {$errorMessage}",
                    'error' => $errorBody,
                ];
            }

            return [
                'success' => true,
                'products' => $response->json()['products'] ?? [],
            ];
        } catch (ConnectionException $e) {
            Log::error('Shopify connection failed', [
                'shop_id' => $shop->id,
                'shop_domain' => $config['shop_domain'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Cannot connect to Shopify store. Please check the shop domain and network connection.',
            ];
        } catch (\Exception $e) {
            Log::error('Shopify fetch products failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Shopify error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Fetch products from Shopify and sync to local database
     */
    public function syncFromPlatform(Shop $shop, ?array $options = []): array
    {
        $fetchResult = $this->fetchProductsFromPlatform($shop, $options);

        if (! $fetchResult['success']) {
            return $fetchResult;
        }

        $products = $fetchResult['products'];
        $synced = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($products as $shopifyProduct) {
            try {
                // Shopify products can have multiple variants, process each variant
                foreach ($shopifyProduct['variants'] as $variant) {
                    $result = $this->syncSingleVariant($shop, $shopifyProduct, $variant);
                    if ($result['success']) {
                        $synced++;
                        if ($result['action'] === 'created') {
                            $created++;
                        } else {
                            $updated++;
                        }
                    } else {
                        $errors[] = $result['message'];
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Product {$shopifyProduct['id']}: {$e->getMessage()}";
                Log::error('Shopify product sync error', [
                    'product_id' => $shopifyProduct['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'synced' => $synced,
            'created' => $created,
            'updated' => $updated,
            'total_fetched' => count($products),
            'errors' => $errors,
        ];
    }

    /**
     * Sync a single Shopify variant as a product
     */
    public function syncSingleVariant(Shop $shop, array $shopifyProduct, array $variant): array
    {
        // Prevent auto-sync loop
        ProductObserver::$syncInProgress = true;

        try {
            $sku = $variant['sku'] ?? null;

            // If no SKU, generate one
            if (empty($sku)) {
                $sku = Product::generateSku('SH');
            }

            // 1. Try to find existing product via platform sync record
            $product = null;
            $action = 'updated';

            $existingSync = ProductEcommerceSync::where('shop_id', $shop->id)
                ->where('platform', 'shopify')
                ->where('platform_product_id', (string) $variant['id'])
                ->first();

            if ($existingSync) {
                $product = Product::find($existingSync->product_id);
            }

            // 2. Fallback: find by SKU
            if (! $product) {
                $product = Product::where('sku', $sku)->first();
            }

            $productName = $shopifyProduct['title'];
            if (count($shopifyProduct['variants']) > 1 && ! empty($variant['title']) && $variant['title'] !== 'Default Title') {
                $productName .= ' - '.$variant['title'];
            }

            // Download images if syncing from platform
            $imagePath = null;
            if (! empty($shopifyProduct['images']) && is_array($shopifyProduct['images'])) {
                $imageUrls = $this->imageDownloadService->extractImageUrls($shopifyProduct['images'], 'shopify');
                if (! empty($imageUrls)) {
                    $downloadedImages = $this->imageDownloadService->downloadImages($imageUrls);
                    // Use first image as primary product image
                    if (! empty($downloadedImages)) {
                        $imagePath = $downloadedImages[0];
                    }
                }
            }

            if (! $product) {
                // Create new product (with race condition protection)
                $productData = [
                    'name' => $productName,
                    'slug' => Str::slug($productName),
                    'description' => strip_tags($shopifyProduct['body_html'] ?? ''),
                    'sku' => $sku,
                    'category_id' => $this->resolveCategoryFromShopify($shopifyProduct),
                    'cost_price' => $this->resolvePurchaseCost($variant),
                    'selling_price' => $variant['price'] ?? 0,
                    'stock_quantity' => $variant['inventory_quantity'] ?? 0,
                    'track_stock' => ($variant['inventory_management'] ?? null) === 'shopify',
                    'status' => $shopifyProduct['status'] === 'active' ? 'active' : 'inactive',
                ];

                // Add image path if downloaded successfully
                if ($imagePath) {
                    $productData['image'] = $imagePath;
                }

                try {
                    $product = Product::create($productData);
                    $action = 'created';
                } catch (QueryException $e) {
                    // Race condition: another request created this product concurrently
                    $product = Product::where('sku', $sku)->first();
                    if (! $product) {
                        throw $e;
                    }
                    Log::info('Shopify product sync: resolved race condition', ['sku' => $sku]);
                }
            } else {
                // Update existing product
                $updateData = [
                    'name' => $productName,
                    'description' => strip_tags($shopifyProduct['body_html'] ?? ''),
                    'selling_price' => $variant['price'] ?? 0,
                    'stock_quantity' => $variant['inventory_quantity'] ?? 0,
                    'track_stock' => ($variant['inventory_management'] ?? null) === 'shopify',
                    'status' => $shopifyProduct['status'] === 'active' ? 'active' : 'inactive',
                ];

                if ($imagePath) {
                    $updateData['image'] = $imagePath;
                }

                // Never clobber a purchase cost that already exists locally — it
                // was either entered by staff or captured from a purchase order.
                if (! $product->hasPurchaseCost()) {
                    $resolvedCost = $this->resolvePurchaseCost($variant);
                    if ($resolvedCost !== null) {
                        $updateData['cost_price'] = $resolvedCost;
                    }
                }

                // Always resolve category from platform data
                $resolvedCategoryId = $this->resolveCategoryFromShopify($shopifyProduct);
                $defaultCategoryId = $this->getDefaultCategoryId();
                // Update category if product is on default or resolved category is a real one
                if ($product->category_id === $defaultCategoryId || $product->category_id === 1 || $resolvedCategoryId !== $defaultCategoryId) {
                    $updateData['category_id'] = $resolvedCategoryId;
                }

                $product->update($updateData);
            }

            // Attach product to shop via pivot table
            $pivot = [
                'stock_quantity' => intval($variant['inventory_quantity'] ?? 0),
                'selling_price' => $variant['price'] ?? null,
                'is_active' => ($shopifyProduct['status'] ?? '') === 'active',
            ];

            // Only written when Shopify supplied a real unit cost, so a re-sync
            // cannot wipe a shop-specific cost that was entered locally.
            $resolvedPivotCost = $this->resolvePurchaseCost($variant);
            if ($resolvedPivotCost !== null) {
                $pivot['cost_price'] = $resolvedPivotCost;
            }

            $product->shops()->syncWithoutDetaching([$shop->id => $pivot]);

            // Create or update sync record
            $sync = ProductEcommerceSync::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'platform' => 'shopify',
                ],
                [
                    'platform_product_id' => (string) $variant['id'],
                    'platform_name' => $productName,
                    'platform_sku' => $sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'from_platform',
                    'last_synced_at' => now(),
                    'platform_data' => [
                        'product_id' => $shopifyProduct['id'],
                        'variant_id' => $variant['id'],
                        'price' => $variant['price'],
                        'compare_at_price' => $variant['compare_at_price'] ?? null,
                        'inventory_quantity' => $variant['inventory_quantity'] ?? 0,
                        'inventory_management' => $variant['inventory_management'] ?? null,
                        'handle' => $shopifyProduct['handle'] ?? null,
                        'images' => $shopifyProduct['images'] ?? [],
                    ],
                ]
            );

            return ['success' => true, 'action' => $action, 'product_id' => $product->id];
        } finally {
            // Re-enable auto-sync
            ProductObserver::$syncInProgress = false;
        }
    }

    /**
     * Push a product to Shopify
     */
    public function syncToPlatform(Shop $shop, Product $product, ?array $options = []): array
    {
        $config = $shop->getIntegrationConfig('shopify');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'Shopify integration not enabled'];
        }

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            // Check if product already exists on platform
            $sync = $product->getSyncRecord($shop->id, 'shopify');

            $productData = [
                'product' => [
                    'title' => $options['platform_name'] ?? $product->name,
                    'body_html' => $product->description ?? '',
                    'vendor' => $options['vendor'] ?? config('app.name'),
                    'product_type' => $options['product_type'] ?? 'General',
                    'status' => $product->isActive() ? 'active' : 'draft',
                    'variants' => [
                        [
                            'sku' => $product->sku,
                            'price' => (string) $product->selling_price,
                            // Only ever an explicitly supplied "was" price. This used
                            // to fall back to the purchase cost, which published what
                            // the shop paid as a public strike-through price.
                            'compare_at_price' => isset($options['compare_at_price'])
                                ? (string) $options['compare_at_price']
                                : null,
                            'inventory_management' => $product->track_stock ? 'shopify' : null,
                            'inventory_quantity' => $product->stock_quantity,
                        ],
                    ],
                ],
            ];

            if ($sync && $sync->platform_product_id) {
                // Update existing variant
                $platformData = $sync->platform_data ?? [];
                $productId = $platformData['product_id'] ?? null;

                if (! $productId) {
                    return ['success' => false, 'message' => 'Missing Shopify product ID'];
                }

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->put("https://{$shopDomain}/admin/api/2024-01/variants/{$sync->platform_product_id}.json", [
                    'variant' => $productData['product']['variants'][0],
                ]);
            } else {
                // Create new product
                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])->post("https://{$shopDomain}/admin/api/2024-01/products.json", $productData);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to push product to Shopify',
                    'error' => $response->body(),
                ];
            }

            $shopifyData = $response->json();
            $shopifyProduct = $shopifyData['product'] ?? null;
            $shopifyVariant = $shopifyData['variant'] ?? ($shopifyProduct['variants'][0] ?? null);

            if (! $shopifyVariant) {
                return ['success' => false, 'message' => 'Failed to get variant data from response'];
            }

            // Update sync record
            if ($sync) {
                $sync->update([
                    'platform_product_id' => (string) $shopifyVariant['id'],
                    'platform_name' => $shopifyProduct['title'] ?? $product->name,
                    'platform_sku' => $product->sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'to_platform',
                    'last_synced_at' => now(),
                    'platform_data' => [
                        'product_id' => $shopifyProduct['id'],
                        'variant_id' => $shopifyVariant['id'],
                        'handle' => $shopifyProduct['handle'] ?? null,
                    ],
                ]);
            } else {
                ProductEcommerceSync::create([
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'platform' => 'shopify',
                    'platform_product_id' => (string) $shopifyVariant['id'],
                    'platform_name' => $shopifyProduct['title'],
                    'platform_sku' => $product->sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'to_platform',
                    'last_synced_at' => now(),
                    'platform_data' => [
                        'product_id' => $shopifyProduct['id'],
                        'variant_id' => $shopifyVariant['id'],
                    ],
                ]);
            }

            return [
                'success' => true,
                'message' => 'Product synced to Shopify',
                'platform_product_id' => $shopifyVariant['id'],
            ];
        } catch (\Exception $e) {
            Log::error('Shopify sync to platform failed', [
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync product inventory/stock only
     */
    public function syncInventory(Shop $shop, Product $product): array
    {
        $sync = $product->getSyncRecord($shop->id, 'shopify');

        if (! $sync || ! $sync->platform_product_id) {
            return ['success' => false, 'message' => 'Product not synced with Shopify'];
        }

        $config = $shop->getIntegrationConfig('shopify');

        try {
            $shopDomain = $this->normalizeShopDomain($config['shop_domain']);
            $accessToken = Crypt::decrypt($config['access_token']);

            // First, get the inventory item ID for this variant
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->get("https://{$shopDomain}/admin/api/2024-01/variants/{$sync->platform_product_id}.json");

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Failed to get variant data'];
            }

            $variant = $response->json()['variant'] ?? null;
            $inventoryItemId = $variant['inventory_item_id'] ?? null;

            if (! $inventoryItemId) {
                return ['success' => false, 'message' => 'No inventory item ID found'];
            }

            // Get location ID (first available location)
            $locationsResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
            ])->get("https://{$shopDomain}/admin/api/2024-01/locations.json");

            $locations = $locationsResponse->json()['locations'] ?? [];
            $locationId = $locations[0]['id'] ?? null;

            if (! $locationId) {
                return ['success' => false, 'message' => 'No location found'];
            }

            // Update inventory level
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type' => 'application/json',
            ])->post("https://{$shopDomain}/admin/api/2024-01/inventory_levels/set.json", [
                'location_id' => $locationId,
                'inventory_item_id' => $inventoryItemId,
                'available' => $product->stock_quantity,
            ]);

            if (! $response->successful()) {
                return ['success' => false, 'message' => 'Failed to update inventory'];
            }

            $sync->update(['last_synced_at' => now()]);

            return ['success' => true, 'message' => 'Inventory updated'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Normalize shop domain
     */
    protected function normalizeShopDomain(string $domain): string
    {
        $domain = str_replace(['https://', 'http://'], '', $domain);
        $domain = rtrim($domain, '/');

        if (! str_contains($domain, '.myshopify.com')) {
            $domain .= '.myshopify.com';
        }

        return $domain;
    }

    /**
     * Resolve a genuine purchase cost from a Shopify variant payload.
     *
     * `compare_at_price` is the struck-through "was" price and is always at or
     * above what the customer pays, so using it as a cost guaranteed a negative
     * margin. Shopify's real unit cost lives on the InventoryItem
     * (`inventory_item.cost`); when the payload does not carry it this returns
     * NULL so the cost stays visibly unset rather than silently wrong.
     *
     * @param  array<string, mixed>  $variant
     */
    protected function resolvePurchaseCost(array $variant): ?float
    {
        $candidates = [
            $variant['inventory_item']['cost'] ?? null,
            $variant['cost'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && is_numeric($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve a local Category from Shopify product data.
     * Uses product_type as the category, upserts by slug.
     *
     * @param  array{product_type?: string, tags?: string}  $shopifyProduct
     */
    protected function resolveCategoryFromShopify(array $shopifyProduct): int
    {
        $productType = trim($shopifyProduct['product_type'] ?? '');

        if (empty($productType)) {
            return $this->getDefaultCategoryId();
        }

        $slug = Str::slug($productType);

        $category = Category::withTrashed()
            ->where('slug', $slug)
            ->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            return $category->id;
        }

        $category = Category::create([
            'name' => $productType,
            'slug' => $slug,
            'status' => 'active',
        ]);

        Log::info('Category created from Shopify sync', [
            'category_id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ]);

        return $category->id;
    }

    /**
     * Get the default category ID (first category or create one).
     */
    protected function getDefaultCategoryId(): int
    {
        $default = Category::where('slug', 'uncategorized')->first();

        if ($default) {
            return $default->id;
        }

        $default = Category::create([
            'name' => 'Uncategorized',
            'slug' => 'uncategorized',
            'status' => 'active',
        ]);

        return $default->id;
    }
}
