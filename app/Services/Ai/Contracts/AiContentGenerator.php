<?php

namespace App\Services\Ai\Contracts;

interface AiContentGenerator
{
    /**
     * Generate marketing content for a campaign / post.
     *
     * @param  array{
     *     campaign_name?: string,
     *     campaign_type?: string,
     *     product_name?: string,
     *     product_description?: string|null,
     *     product_price?: float|string|null,
     *     promo_price?: float|string|null,
     *     currency?: string|null,
     *     tone?: string|null,
     *     language?: string|null,
     *     platform?: string|null,
     *     landing_url?: string|null,
     *     promo_code?: string|null,
     *     extra_instructions?: string|null,
     * }  $context
     * @return array{
     *     caption: string,
     *     hashtags: array<int, string>,
     *     call_to_action: string,
     *     provider: string,
     *     model: string|null,
     *     raw: mixed,
     * }
     */
    public function generateCampaignContent(array $context): array;

    public function name(): string;
}
