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

class WooCommerceProductSyncService
{
    public function __construct(
        protected ImageDownloadService $imageDownloadService
    ) {}

    /**
     * Fetch product list from WooCommerce API without processing.
     *
     * @return array{success: bool, products?: array, message?: string}
     */
    public function fetchProductsFromPlatform(Shop $shop, ?array $options = []): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['success' => false, 'message' => 'WooCommerce API credentials are incomplete. Please re-enter your Consumer Key and Consumer Secret in Shop settings.'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $perPage = $options['per_page'] ?? 100;
            $page = $options['page'] ?? 1;

            $response = Http::timeout(30)
                ->withBasicAuth($consumerKey, $consumerSecret)
                ->get("{$storeUrl}/wp-json/wc/v3/products", [
                    'per_page' => $perPage,
                    'page' => $page,
                    'status' => 'publish',
                ]);

            if (! $response->successful()) {
                $statusCode = $response->status();
                $errorBody = $response->json() ?? ['message' => $response->body()];

                Log::error('WooCommerce API request failed', [
                    'shop_id' => $shop->id,
                    'store_url' => $storeUrl,
                    'status_code' => $statusCode,
                    'error' => $errorBody,
                ]);

                $errorMessage = $errorBody['message'] ?? $errorBody['code'] ?? 'Unknown error';
                $errorCode = $errorBody['code'] ?? null;

                // Provide helpful message for common errors
                if ($statusCode === 401) {
                    if ($errorCode === 'woocommerce_rest_cannot_view') {
                        $errorMessage = 'Authentication failed: API keys do not have READ permissions. Please check your WooCommerce REST API key permissions (should be "Read" or "Read/Write").';
                    } else {
                        $errorMessage = 'Authentication failed: Invalid API credentials. Please verify your Consumer Key and Consumer Secret in WooCommerce.';
                    }
                } elseif ($statusCode === 404) {
                    $errorMessage = 'WooCommerce REST API endpoint not found. Please verify the store URL is correct and WooCommerce is installed.';
                }

                return [
                    'success' => false,
                    'message' => "Failed to fetch products from WooCommerce (HTTP {$statusCode}): {$errorMessage}",
                    'error' => $errorBody,
                ];
            }

            return [
                'success' => true,
                'products' => $response->json(),
            ];
        } catch (ConnectionException $e) {
            Log::error('WooCommerce connection failed', [
                'shop_id' => $shop->id,
                'store_url' => $config['store_url'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Cannot connect to WooCommerce store. Please check the store URL and network connection.',
            ];
        } catch (\Exception $e) {
            Log::error('WooCommerce fetch products failed', [
                'shop_id' => $shop->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'WooCommerce error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Fetch products from WooCommerce and sync to local database
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

        foreach ($products as $wcProduct) {
            try {
                $result = $this->syncSingleProduct($shop, $wcProduct);
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
            } catch (\Exception $e) {
                $errors[] = "Product {$wcProduct['id']}: {$e->getMessage()}";
                Log::error('WooCommerce product sync error', [
                    'product_id' => $wcProduct['id'],
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
     * Sync a single product from WooCommerce
     */
    public function syncSingleProduct(Shop $shop, array $wcProduct): array
    {
        // Prevent auto-sync loop
        ProductObserver::$syncInProgress = true;

        try {
            $sku = $wcProduct['sku'] ?? null;

            // If no SKU, generate one
            if (empty($sku)) {
                $sku = Product::generateSku('WC');
            }

            // 1. Try to find existing product via platform sync record
            $product = null;
            $action = 'updated';

            $existingSync = ProductEcommerceSync::where('shop_id', $shop->id)
                ->where('platform', 'woocommerce')
                ->where('platform_product_id', (string) $wcProduct['id'])
                ->first();

            if ($existingSync) {
                $product = Product::find($existingSync->product_id);
            }

            // 2. Fallback: find by SKU
            if (! $product) {
                $product = Product::where('sku', $sku)->first();
            }

            // Download images if syncing from platform
            $imagePath = null;
            if (! empty($wcProduct['images']) && is_array($wcProduct['images'])) {
                $imageUrls = $this->imageDownloadService->extractImageUrls($wcProduct['images'], 'woocommerce');
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
                $slug = Str::slug($wcProduct['name']);

                // Ensure slug is unique
                $originalSlug = $slug;
                $counter = 1;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $originalSlug.'-'.$counter;
                    $counter++;
                }

                $productData = [
                    'name' => $wcProduct['name'],
                    'slug' => $slug,
                    'description' => strip_tags($wcProduct['description'] ?? ''),
                    'sku' => $sku,
                    'category_id' => $this->resolveCategoryFromWooCommerce($wcProduct['categories'] ?? []),
                    'cost_price' => $this->resolvePurchaseCost($wcProduct),
                    'selling_price' => floatval($wcProduct['price'] ?? 0) ?: 0,
                    'stock_quantity' => intval($wcProduct['stock_quantity'] ?? 0),
                    'track_stock' => $wcProduct['manage_stock'] ?? false,
                    'status' => $wcProduct['status'] === 'publish' ? 'active' : 'inactive',
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
                    Log::info('WooCommerce product sync: resolved race condition', ['sku' => $sku]);
                }
            } else {
                // Update existing product
                $updateData = [
                    'name' => $wcProduct['name'],
                    'description' => strip_tags($wcProduct['description'] ?? ''),
                    'selling_price' => floatval($wcProduct['price'] ?? 0) ?: 0,
                    'stock_quantity' => intval($wcProduct['stock_quantity'] ?? 0),
                    'track_stock' => $wcProduct['manage_stock'] ?? false,
                    'status' => $wcProduct['status'] === 'publish' ? 'active' : 'inactive',
                ];

                if ($imagePath) {
                    $updateData['image'] = $imagePath;
                }

                // Never clobber a purchase cost that already exists locally — it
                // was either entered by staff or captured from a purchase order,
                // and WooCommerce has nothing more authoritative to replace it with.
                if (! $product->hasPurchaseCost()) {
                    $resolvedCost = $this->resolvePurchaseCost($wcProduct);
                    if ($resolvedCost !== null) {
                        $updateData['cost_price'] = $resolvedCost;
                    }
                }

                // Always resolve category from platform data
                $resolvedCategoryId = $this->resolveCategoryFromWooCommerce($wcProduct['categories'] ?? []);
                $defaultCategoryId = $this->getDefaultCategoryId();
                // Update category if product is on default or current category matches the "General Products" fallback
                if ($product->category_id === $defaultCategoryId || $product->category_id === 1 || $resolvedCategoryId !== $defaultCategoryId) {
                    $updateData['category_id'] = $resolvedCategoryId;
                }

                $product->update($updateData);
            }

            // Attach product to shop via pivot table
            $product->shops()->syncWithoutDetaching([
                $shop->id => [
                    'stock_quantity' => intval($wcProduct['stock_quantity'] ?? 0),
                    'selling_price' => floatval($wcProduct['price'] ?? 0) ?: null,
                    'is_active' => ($wcProduct['status'] ?? '') === 'publish',
                ],
            ]);

            // Create or update sync record
            $sync = ProductEcommerceSync::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'platform' => 'woocommerce',
                ],
                [
                    'platform_product_id' => (string) $wcProduct['id'],
                    'platform_name' => $wcProduct['name'],
                    'platform_sku' => $sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'from_platform',
                    'last_synced_at' => now(),
                    'platform_data' => [
                        'price' => $wcProduct['price'],
                        'regular_price' => $wcProduct['regular_price'],
                        'sale_price' => $wcProduct['sale_price'] ?? null,
                        'stock_quantity' => $wcProduct['stock_quantity'] ?? 0,
                        'manage_stock' => $wcProduct['manage_stock'] ?? false,
                        'permalink' => $wcProduct['permalink'] ?? null,
                        'images' => $wcProduct['images'] ?? [],
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
     * Push a product to WooCommerce
     */
    public function syncToPlatform(Shop $shop, Product $product, ?array $options = []): array
    {
        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config) || ! ($config['enabled'] ?? false)) {
            return ['success' => false, 'message' => 'WooCommerce integration not enabled'];
        }

        if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['success' => false, 'message' => 'WooCommerce API credentials are incomplete. Please re-enter your Consumer Key and Consumer Secret in Shop settings.'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            // Check if product already exists on platform
            $sync = $product->getSyncRecord($shop->id, 'woocommerce');

            $productData = [
                'name' => $options['platform_name'] ?? $product->name,
                'type' => 'simple',
                'sku' => $product->sku,
                'regular_price' => (string) $product->selling_price,
                'description' => $product->description ?? '',
                'manage_stock' => $product->track_stock,
                'stock_quantity' => $product->stock_quantity,
                'status' => $product->isActive() ? 'publish' : 'draft',
            ];

            if ($sync && $sync->platform_product_id) {
                // Update existing product
                $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                    ->put("{$storeUrl}/wp-json/wc/v3/products/{$sync->platform_product_id}", $productData);
            } else {
                // Create new product
                $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                    ->post("{$storeUrl}/wp-json/wc/v3/products", $productData);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Failed to push product to WooCommerce',
                    'error' => $response->body(),
                ];
            }

            $wcProduct = $response->json();

            // Update sync record
            if ($sync) {
                $sync->update([
                    'platform_product_id' => (string) $wcProduct['id'],
                    'platform_name' => $wcProduct['name'],
                    'platform_sku' => $product->sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'to_platform',
                    'last_synced_at' => now(),
                    'platform_data' => [
                        'price' => $wcProduct['price'],
                        'permalink' => $wcProduct['permalink'] ?? null,
                    ],
                ]);
            } else {
                ProductEcommerceSync::create([
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'platform' => 'woocommerce',
                    'platform_product_id' => (string) $wcProduct['id'],
                    'platform_name' => $wcProduct['name'],
                    'platform_sku' => $product->sku,
                    'sync_status' => 'synced',
                    'sync_direction' => 'to_platform',
                    'last_synced_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Product synced to WooCommerce',
                'platform_product_id' => $wcProduct['id'],
                'permalink' => $wcProduct['permalink'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('WooCommerce sync to platform failed', [
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
        $sync = $product->getSyncRecord($shop->id, 'woocommerce');

        if (! $sync || ! $sync->platform_product_id) {
            return ['success' => false, 'message' => 'Product not synced with WooCommerce'];
        }

        $config = $shop->getIntegrationConfig('woocommerce');

        if (empty($config['consumer_key']) || empty($config['consumer_secret'])) {
            return ['success' => false, 'message' => 'WooCommerce API credentials are incomplete. Please re-enter your Consumer Key and Consumer Secret in Shop settings.'];
        }

        try {
            $storeUrl = rtrim($config['store_url'], '/');
            $consumerKey = Crypt::decrypt($config['consumer_key']);
            $consumerSecret = Crypt::decrypt($config['consumer_secret']);

            $response = Http::withBasicAuth($consumerKey, $consumerSecret)
                ->put("{$storeUrl}/wp-json/wc/v3/products/{$sync->platform_product_id}", [
                    'stock_quantity' => $product->stock_quantity,
                    'manage_stock' => $product->track_stock,
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
     * Meta keys used by the common WooCommerce cost-of-goods plugins, in
     * order of preference.
     *
     * @var array<int, string>
     */
    protected const COST_META_KEYS = [
        '_wc_cog_cost',
        '_alg_wc_cog_cost',
        '_cost_of_goods',
        '_wc_cog_cost_purchase',
    ];

    /**
     * Resolve a genuine purchase cost from a WooCommerce product payload.
     *
     * WooCommerce core has NO cost-of-goods field: `regular_price` is the
     * pre-discount SELLING price, not what the shop paid. Mapping it onto
     * `cost_price` made every discounted product sell at a loss on paper, so
     * this now reads a real cost only when a cost-of-goods plugin supplies one
     * via meta and otherwise returns NULL, leaving the cost to be entered by
     * hand or captured from a purchase order.
     *
     * @param  array<string, mixed>  $wcProduct
     */
    protected function resolvePurchaseCost(array $wcProduct): ?float
    {
        $meta = $wcProduct['meta_data'] ?? [];

        if (! is_array($meta)) {
            return null;
        }

        foreach (self::COST_META_KEYS as $key) {
            foreach ($meta as $entry) {
                $entry = is_array($entry) ? $entry : (array) $entry;

                if (($entry['key'] ?? null) !== $key) {
                    continue;
                }

                $value = $entry['value'] ?? null;

                if (! is_scalar($value) || ! is_numeric($value) || (float) $value <= 0) {
                    continue;
                }

                return (float) $value;
            }
        }

        return null;
    }

    /**
     * Resolve a local Category from WooCommerce category data.
     * Upserts categories by slug, preserving parent/child hierarchy.
     *
     * @param  array<int, array{id: int, name: string, slug: string}>  $wcCategories
     */
    protected function resolveCategoryFromWooCommerce(array $wcCategories): int
    {
        if (empty($wcCategories)) {
            return $this->getDefaultCategoryId();
        }

        // Use the first (primary) category from WooCommerce
        $primary = $wcCategories[0];

        $category = Category::withTrashed()
            ->where('slug', $primary['slug'])
            ->first();

        if ($category) {
            if ($category->trashed()) {
                $category->restore();
            }

            return $category->id;
        }

        $category = Category::create([
            'name' => $primary['name'],
            'slug' => $primary['slug'],
            'status' => 'active',
        ]);

        Log::info('Category created from WooCommerce sync', [
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
