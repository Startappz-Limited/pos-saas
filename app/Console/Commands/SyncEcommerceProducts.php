<?php

namespace App\Console\Commands;

use App\Actions\Product\SyncProductFromEcommerce;
use App\Actions\Product\SyncProductToEcommerce;
use App\Models\Shop;
use Illuminate\Console\Command;

class SyncEcommerceProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ecommerce:sync-products
                            {shop : The shop ID or UUID}
                            {--platform= : Platform to sync (woocommerce, shopify, all)}
                            {--direction=both : Direction: from-platform, to-platform, or both}
                            {--limit=100 : Limit number of products to fetch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products between local database and e-commerce platforms';

    /**
     * Execute the console command.
     */
    public function handle(
        SyncProductFromEcommerce $syncFrom,
        SyncProductToEcommerce $syncTo
    ): int {
        $shopIdentifier = $this->argument('shop');
        $platform = $this->option('platform') ?? 'all';
        $direction = $this->option('direction');

        // Find shop
        $shop = is_numeric($shopIdentifier)
            ? Shop::find($shopIdentifier)
            : Shop::where('uuid', $shopIdentifier)->first();

        if (! $shop) {
            $this->error("Shop not found: {$shopIdentifier}");

            return self::FAILURE;
        }

        $this->info("Syncing products for shop: {$shop->name}");

        // Determine which platforms to sync
        $platforms = $platform === 'all'
            ? $shop->getEnabledIntegrations()
            : [$platform];

        if (empty($platforms)) {
            $this->warn('No e-commerce integrations enabled for this shop');

            return self::SUCCESS;
        }

        foreach ($platforms as $platformName) {
            $this->info("Processing platform: {$platformName}");

            // Sync from platform
            if (in_array($direction, ['from-platform', 'both'])) {
                $this->newLine();
                $this->line("🔽 Syncing FROM {$platformName}...");

                $result = $syncFrom->execute($shop, $platformName, [
                    'limit' => $this->option('limit'),
                ]);

                if ($result['success']) {
                    $this->info("✓ Synced {$result['synced']} products");
                    $this->line("  - Created: {$result['created']}");
                    $this->line("  - Updated: {$result['updated']}");

                    if (! empty($result['errors'])) {
                        $this->warn('  Errors:');
                        foreach (array_slice($result['errors'], 0, 5) as $error) {
                            $this->line("    - {$error}");
                        }
                    }
                } else {
                    $this->error("✗ Failed: {$result['message']}");
                }
            }

            // Sync to platform
            if (in_array($direction, ['to-platform', 'both'])) {
                $this->newLine();
                $this->line("🔼 Syncing TO {$platformName}...");
                $this->warn('Bulk sync to platform not yet implemented. Use per-product sync.');
            }
        }

        $this->newLine();
        $this->info('✓ Sync completed');

        return self::SUCCESS;
    }
}
