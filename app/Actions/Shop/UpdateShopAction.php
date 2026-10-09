<?php

namespace App\Actions\Shop;

use App\Models\Shop;
use App\Services\Integration\ShopifyService;
use App\Services\Integration\WooCommerceService;
use Illuminate\Support\Facades\DB;

class UpdateShopAction
{
    public function __construct(
        protected WooCommerceService $wooCommerceService,
        protected ShopifyService $shopifyService
    ) {}

    public function execute(Shop $shop, array $data): Shop
    {
        return DB::transaction(function () use ($shop, $data) {
            // Settings reach this action one section at a time — the tax panel,
            // the sale-notification switches, the integration credentials — so a
            // partial payload has to be merged into what is already stored.
            // Assigning it wholesale would wipe every section the form did not post.
            //
            // This runs BEFORE processIntegrations, which owns the integrations
            // key and prunes the ones being disabled. Merging afterwards would
            // resurrect an integration the user just removed.
            if (array_key_exists('settings', $data) && is_array($data['settings'])) {
                $data['settings'] = array_replace_recursive($shop->settings ?? [], $data['settings']);
            }

            // Process integration configurations
            $data = $this->processIntegrations($shop, $data);

            // Update shop details
            $shop->update($data);

            // Update manager assignment if changed
            if (isset($data['manager_id'])) {
                // Remove old manager from users if exists
                if ($shop->manager_id && $shop->manager_id !== $data['manager_id']) {
                    $shop->users()->detach($shop->manager_id);
                }

                // Add new manager to users if not already attached
                if ($data['manager_id'] && ! $shop->users()->where('user_id', $data['manager_id'])->exists()) {
                    $shop->users()->attach($data['manager_id']);
                }
            }

            // Sync additional users if provided
            if (isset($data['user_ids']) && is_array($data['user_ids'])) {
                // Always keep the manager in the users list
                $userIds = $data['user_ids'];
                if ($shop->manager_id && ! in_array($shop->manager_id, $userIds)) {
                    $userIds[] = $shop->manager_id;
                }
                $shop->users()->sync($userIds);
            }

            return $shop->fresh(['manager', 'creator', 'updater', 'users']);
        });
    }

    /**
     * Process and encrypt integration configurations
     * Preserves existing integrations not provided in update
     *
     * @param  Shop  $shop  Existing shop instance
     * @param  array  $data  Input data
     * @return array Data with encrypted integration credentials
     */
    protected function processIntegrations(Shop $shop, array $data): array
    {
        if (empty($data['integrations'])) {
            return $data;
        }

        $settings = $data['settings'] ?? $shop->settings ?? [];
        $existingIntegrations = $settings['integrations'] ?? [];
        $integrations = $existingIntegrations;

        // Process WooCommerce integration
        if (isset($data['integrations']['woocommerce'])) {
            $wooConfig = $data['integrations']['woocommerce'];

            // If disabled or empty, remove integration
            if (empty($wooConfig['enabled']) && empty($wooConfig['store_url'])) {
                unset($integrations['woocommerce']);
            } elseif (! empty($wooConfig['store_url']) || ! empty($wooConfig['consumer_key'])) {
                // Preserve existing encrypted credentials when fields are left blank
                $existingWoo = $existingIntegrations['woocommerce'] ?? [];
                if (empty($wooConfig['consumer_key']) || $wooConfig['consumer_key'] === '••••••••••••••••') {
                    unset($wooConfig['consumer_key']);
                }
                if (empty($wooConfig['consumer_secret'])) {
                    unset($wooConfig['consumer_secret']);
                }

                $encrypted = $this->wooCommerceService->encryptCredentials($wooConfig);

                // Restore existing encrypted values for fields not provided
                if (! isset($wooConfig['consumer_key']) && isset($existingWoo['consumer_key'])) {
                    $encrypted['consumer_key'] = $existingWoo['consumer_key'];
                }
                if (! isset($wooConfig['consumer_secret']) && isset($existingWoo['consumer_secret'])) {
                    $encrypted['consumer_secret'] = $existingWoo['consumer_secret'];
                }

                $integrations['woocommerce'] = $encrypted;
            }
        }

        // Process Shopify integration
        if (isset($data['integrations']['shopify'])) {
            $shopifyConfig = $data['integrations']['shopify'];

            // If disabled or empty, remove integration
            if (empty($shopifyConfig['enabled']) && empty($shopifyConfig['shop_domain'])) {
                unset($integrations['shopify']);
            } elseif (! empty($shopifyConfig['shop_domain']) || ! empty($shopifyConfig['access_token'])) {
                // Preserve existing encrypted token when field is left blank
                $existingShopify = $existingIntegrations['shopify'] ?? [];
                if (empty($shopifyConfig['access_token'])) {
                    unset($shopifyConfig['access_token']);
                }

                $encrypted = $this->shopifyService->encryptCredentials($shopifyConfig);

                if (! isset($shopifyConfig['access_token']) && isset($existingShopify['access_token'])) {
                    $encrypted['access_token'] = $existingShopify['access_token'];
                }

                $integrations['shopify'] = $encrypted;
            }
        }

        // Update settings with processed integrations
        $settings['integrations'] = $integrations;
        $data['settings'] = $settings;
        unset($data['integrations']); // Remove from top level after processing

        return $data;
    }
}
