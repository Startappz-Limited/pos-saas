<?php

namespace App\Services\Ai\Drivers;

/**
 * Inert driver that produces deterministic, template-based content. Used as a
 * safe default when no provider is configured.
 */
class NullDriver extends AbstractAiDriver
{
    public function name(): string
    {
        return 'null';
    }

    public function generateCampaignContent(array $context): array
    {
        $product = $context['product_name'] ?? ($context['campaign_name'] ?? 'this product');
        $price = $context['promo_price'] ?? $context['product_price'] ?? null;
        $currency = $context['currency'] ?? 'KES';
        $url = $context['landing_url'] ?? null;
        $promo = $context['promo_code'] ?? null;

        $caption = "Discover {$product}."
            .($price ? " Available now from {$currency} {$price}." : '')
            .($promo ? " Use promo code {$promo}." : '')
            .($url ? " Shop here: {$url}" : '');

        return [
            'caption' => $caption,
            'hashtags' => ['fitness', 'wellness', 'shopnow', 'newarrival'],
            'call_to_action' => 'Shop now',
            'provider' => 'null',
            'model' => null,
            'raw' => null,
        ];
    }
}
