<?php

namespace App\Enums;

enum CampaignType: string
{
    case AWARENESS = 'awareness';
    case LEAD_GENERATION = 'lead_generation';
    case SALES = 'sales';
    case ENGAGEMENT = 'engagement';
    case RETENTION = 'retention';
    case PRODUCT_LAUNCH = 'product_launch';
    case SEASONAL = 'seasonal';
    case PROMOTION = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::AWARENESS => 'Brand Awareness',
            self::LEAD_GENERATION => 'Lead Generation',
            self::SALES => 'Direct Sales',
            self::ENGAGEMENT => 'Engagement',
            self::RETENTION => 'Customer Retention',
            self::PRODUCT_LAUNCH => 'Product Launch',
            self::SEASONAL => 'Seasonal',
            self::PROMOTION => 'Promotion',
        };
    }

    public function primaryMetric(): string
    {
        return match ($this) {
            self::AWARENESS => 'impressions',
            self::LEAD_GENERATION => 'conversions',
            self::SALES => 'revenue',
            self::ENGAGEMENT => 'clicks',
            self::RETENTION => 'conversions',
            self::PRODUCT_LAUNCH => 'reach',
            self::SEASONAL => 'revenue',
            self::PROMOTION => 'revenue',
        };
    }
}
