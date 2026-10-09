<?php

namespace App\Actions\Shop;

use App\Enums\ShopStatus;
use App\Models\Shop;
use Illuminate\Support\Arr;
use App\Services\Integration\ShopifyService;
use App\Services\Integration\WooCommerceService;
use Illuminate\Support\Facades\DB;

class CreateShopAction
{
    public function __construct(
        protected WooCommerceService $wooCommerceService,
        protected ShopifyService $shopifyService
    ) {}

  public function execute(array $data): Shop
    {
        return DB::transaction(function () use ($data) {
            // Ensure status defaults to active if not provided
            $data['status'] = $data['status'] ?? ShopStatus::ACTIVE;

            // Generate a unique shop code if empty
            if (empty($data['code'])) {
                do {
                    $shopCode = 'SHOP-' . implode('',
                        Arr::random(range(0, 9), 4)
                    ); 
                } while (Shop::where('code', $shopCode)->exists());

                $data['code'] = $shopCode;
            }

            // Process integration configurations
            $data = $this->processIntegrations($data);

            // Create the shop
            $shop = Shop::create($data);

            // Assign manager to shop users if manager is provided
            if (! empty($data['manager_id'])) {
                $shop->users()->attach($data['manager_id']);
            }

            // Assign additional users if provided
            if (! empty($data['user_ids']) && is_array($data['user_ids'])) {
                $userIds = array_diff(
                    $data['user_ids'],
                    [$data['manager_id'] ?? null]
                );

                $shop->users()->attach($userIds);
            }

            return $shop->fresh(['manager', 'creator', 'updater', 'users']);
        });
    }
    /**
     * Process and encrypt integration configurations
     *
     * @param  array  $data  Input data
     * @return array Data with encrypted integration credentials
     */
    protected function processIntegrations(array $data): array
    {
        if (empty($data['integrations'])) {
            return $data;
        }

        $settings = $data['settings'] ?? [];
        $integrations = [];

        // Process WooCommerce integration
        if (! empty($data['integrations']['woocommerce'])) {
            $wooConfig = $data['integrations']['woocommerce'];
            if (! empty($wooConfig['store_url']) || ! empty($wooConfig['consumer_key'])) {
                $integrations['woocommerce'] = $this->wooCommerceService->encryptCredentials($wooConfig);
            }
        }

        // Process Shopify integration
        if (! empty($data['integrations']['shopify'])) {
            $shopifyConfig = $data['integrations']['shopify'];
            if (! empty($shopifyConfig['shop_domain']) || ! empty($shopifyConfig['access_token'])) {
                $integrations['shopify'] = $this->shopifyService->encryptCredentials($shopifyConfig);
            }
        }

        // Merge integrations into settings
        if (! empty($integrations)) {
            $settings['integrations'] = $integrations;
        }

        $data['settings'] = $settings;
        unset($data['integrations']); // Remove from top level after processing

        return $data;
    }
}
