# Product Sync Queue Implementation

## Overview

Product synchronization now runs asynchronously in the background using Laravel's queue system. This prevents timeouts when syncing large product catalogs and improves user experience.

## Features

### 1. Queued Product Sync
- All bulk product sync operations are now queued
- Prevents HTTP request timeouts for large datasets
- Jobs are retried up to 3 times on failure
- 5-minute timeout per job

### 2. Automatic Image Download
- Product images are automatically downloaded from e-commerce platforms during sync
- Images are saved to `storage/app/public/products/`
- Only applies when syncing **from platform** (import)
- Supports both WooCommerce and Shopify image formats
- Failed image downloads are logged but don't stop product sync

### 3. Comprehensive Logging
- Job start/completion logged with details
- Individual product errors logged
- Image download failures logged as warnings
- Failed jobs logged after all retries exhausted

## Setup Instructions

### 1. Ensure Queue Tables Exist
```bash
php artisan migrate
```

This should create the `jobs`, `job_batches`, and `failed_jobs` tables if they don't exist.

### 2. Configure Queue Connection
The queue is already configured to use `database` driver by default. Check your `.env`:

```env
QUEUE_CONNECTION=database
```

For better performance with large-scale operations, consider Redis:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3. Create Storage Symlink
Make sure product images are publicly accessible:

```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`.

### 4. Start Queue Worker

**For Development:**
```bash
php artisan queue:work --queue=default --tries=3 --timeout=300
```

Or if using Herd/Valet:
```bash
# In a separate terminal window/tab
php artisan queue:listen
```

**For Production (using Supervisor):**

Create `/etc/supervisor/conf.d/laravel-worker.conf`:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasec=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

## How It Works

### Product Sync Flow

1. **User Initiates Sync** (Products Index Page)
   - Selects shop, platform, direction, and limit
   - Clicks "Sync Products"

2. **Job Dispatched** (ProductSyncController)
   - `SyncProductsJob` is queued with parameters
   - User receives immediate feedback: "Product sync job queued successfully"

3. **Job Processes** (SyncProductsJob)
   - Runs in background via queue worker
   - Calls `SyncProductFromEcommerce` for imports
   - Calls `SyncProductToEcommerce` for exports
   - Handles both directions if "Both" selected

4. **Images Downloaded** (During Import)
   - `ImageDownloadService` extracts image URLs from API responses
   - Downloads images with 30-second timeout per image
   - Saves to `storage/app/public/products/`
   - Updates product record with local path
   - Failed downloads logged but don't block sync

5. **Results Logged**
   - Check `storage/logs/laravel.log` for detailed results
   - Successful syncs: product counts, actions taken
   - Errors: specific error messages with context

### Single Product Sync

Single product sync from the product detail page remains **synchronous** (not queued) since it's just one product and completes quickly.

## Image Storage

### Directory Structure
```
storage/app/public/products/
├── abc123def456...jpg
├── ghi789jkl012...png
└── mno345pqr678...webp
```

### Supported Formats
- JPEG/JPG
- PNG
- GIF
- WebP
- SVG

### Image Handling
- **Filename**: Random 40-character string + extension
- **Primary Image**: First image in platform array becomes product's main image
- **Updates**: Existing products get images updated on re-sync
- **Fallback**: If download fails, product still syncs without image

## Monitoring

### Check Queued Jobs
```bash
# Count pending jobs
mysql -u root -e "USE fitness_center; SELECT COUNT(*) FROM jobs;"

# View failed jobs
php artisan queue:failed
```

### Check Logs
```bash
# Real-time log monitoring
tail -f storage/logs/laravel.log

# Last 100 lines
tail -100 storage/logs/laravel.log

# Search for sync jobs
grep "Product sync job" storage/logs/laravel.log
```

### Retry Failed Jobs
```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific failed job
php artisan queue:retry <job-id>

# Flush all failed jobs
php artisan queue:flush
```

## Troubleshooting

### Queue Not Processing
**Problem**: Jobs stay in queue but don't process

**Solutions**:
1. Ensure queue worker is running: `ps aux | grep "queue:work"`
2. Start worker: `php artisan queue:work`
3. Check queue connection in `.env`
4. Verify jobs table exists: `php artisan migrate`

### Images Not Downloading
**Problem**: Products sync but images missing

**Solutions**:
1. Check storage permissions: `chmod -R 775 storage`
2. Create symlink: `php artisan storage:link`
3. Check logs for download errors: `grep "Failed to download image" storage/logs/laravel.log`
4. Verify external URLs are accessible (not behind auth wall)

### Job Timeout
**Problem**: Job fails with "Maximum execution time exceeded"

**Solutions**:
1. Increase job timeout in `SyncProductsJob::$timeout` (default: 300s)
2. Reduce sync limit per job (use smaller batch sizes)
3. Increase PHP `max_execution_time` for CLI
4. Optimize API requests (check platform API limits)

### Memory Issues
**Problem**: Job fails with "Allowed memory size exhausted"

**Solutions**:
1. Increase PHP memory limit for CLI
2. Reduce batch size (sync fewer products per job)
3. Use `--memory=512` flag when starting worker

## API Reference

### ImageDownloadService

```php
// Download multiple images
$imageService = app(ImageDownloadService::class);
$localPaths = $imageService->downloadImages([
    'https://example.com/image1.jpg',
    'https://example.com/image2.png'
], 'products');

// Download single image
$localPath = $imageService->downloadSingleImage(
    'https://example.com/image.jpg', 
    'products'
);

// Extract image URLs from platform data
$urls = $imageService->extractImageUrls(
    $platformImages, 
    'woocommerce' // or 'shopify'
);
```

### Dispatch Sync Job

```php
use App\Jobs\SyncProductsJob;
use App\Models\Shop;

$shop = Shop::find(1);

SyncProductsJob::dispatch(
    shop: $shop,
    platform: 'woocommerce',
    direction: 'from-platform', // 'to-platform', 'both'
    options: ['limit' => 100],
    userId: auth()->user()?->id
);
```

## Performance Notes

- **WooCommerce**: Fetches 100 products per page by default
- **Shopify**: Fetches up to 250 products per request
- **Image Downloads**: 30-second timeout per image
- **Job Processing**: Up to 3 retry attempts
- **Recommended Batch Size**: 50-100 products for initial sync, 250+ for subsequent syncs

## Best Practices

1. **Start Small**: Test with limit of 10 products first
2. **Monitor Logs**: Watch logs during first sync to catch issues early
3. **Platform API Limits**: Be aware of API rate limits (especially Shopify)
4. **Queue Workers**: Run at least 2 worker processes for better throughput
5. **Disk Space**: Monitor storage space for product images
6. **Regular Cleanup**: Consider periodic cleanup of old/unused product images

## Related Files

- **Job**: `app/Jobs/SyncProductsJob.php`
- **Controller**: `app/Http/Controllers/ProductSyncController.php`
- **Image Service**: `app/Services/Integration/ImageDownloadService.php`
- **WooCommerce Service**: `app/Services/Integration/WooCommerceProductSyncService.php`
- **Shopify Service**: `app/Services/Integration/ShopifyProductSyncService.php`
- **Actions**: 
  - `app/Actions/Product/SyncProductFromEcommerce.php`
  - `app/Actions/Product/SyncProductToEcommerce.php`
