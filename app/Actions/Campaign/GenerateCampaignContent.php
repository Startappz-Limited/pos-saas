<?php

namespace App\Actions\Campaign;

use App\Models\Campaign;
use App\Models\Product;
use App\Services\Ai\AiManager;

class GenerateCampaignContent
{
    public function __construct(protected AiManager $ai) {}

    /**
     * Generate marketing copy + hashtags for a campaign, optionally tailored to
     * a specific product. Returns the structured content; persistence is done
     * by the caller (controller / job).
     *
     * @param  array<string, mixed>  $overrides  Optional context overrides
     * @return array{caption: string, hashtags: array<int, string>, call_to_action: string, provider: string, model: string|null, raw: mixed}
     */
    public function handle(Campaign $campaign, ?Product $product = null, array $overrides = []): array
    {
        $aiSettings = $campaign->ai_settings ?? [];
        $driver = $this->ai->driver($overrides['provider'] ?? ($aiSettings['provider'] ?? null));

        $context = array_merge([
            'campaign_name' => $campaign->name,
            'campaign_type' => $campaign->campaign_type?->label(),
            'tone' => $aiSettings['tone'] ?? null,
            'language' => $aiSettings['language'] ?? null,
            'platform' => $overrides['platform'] ?? 'social media',
            'currency' => $campaign->currency,
            'promo_code' => $campaign->promo_code,
            'landing_url' => $campaign->default_landing_url,
        ], $product ? [
            'product_name' => $product->name,
            'product_description' => $product->description,
            'product_price' => $product->selling_price,
            'landing_url' => $this->resolveProductLandingUrl($campaign, $product),
        ] : [], $overrides);

        return array_merge(
            $driver->generateCampaignContent($context),
            ['landing_url' => $context['landing_url'] ?? null],
        );
    }

    private function resolveProductLandingUrl(Campaign $campaign, Product $product): ?string
    {
        $pivotUrl = $product->pivot->landing_url ?? null;
        if (! empty($pivotUrl)) {
            return $pivotUrl;
        }

        // Pull a permalink stored by the e-commerce sync layer (WooCommerce / Shopify).
        $sync = $product->ecommerceSyncs()
            ->where('shop_id', $campaign->shop_id)
            ->latest('last_synced_at')
            ->first();

        $platformData = $sync?->platform_data ?? [];

        return $platformData['permalink']
            ?? $platformData['onlineStoreUrl']
            ?? $platformData['url']
            ?? $campaign->default_landing_url;
    }
}
