<?php

namespace App\Enums;

enum CampaignExpenseType: string
{
    case AD_SPEND = 'ad_spend';
    case CREATIVE = 'creative';
    case PRODUCTION = 'production';
    case AGENCY_FEE = 'agency_fee';
    case INFLUENCER_FEE = 'influencer_fee';
    case PLATFORM_FEE = 'platform_fee';
    case PRINTING = 'printing';
    case DISTRIBUTION = 'distribution';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AD_SPEND => 'Ad Spend',
            self::CREATIVE => 'Creative/Design',
            self::PRODUCTION => 'Production',
            self::AGENCY_FEE => 'Agency Fee',
            self::INFLUENCER_FEE => 'Influencer Fee',
            self::PLATFORM_FEE => 'Platform Fee',
            self::PRINTING => 'Printing',
            self::DISTRIBUTION => 'Distribution',
            self::OTHER => 'Other',
        };
    }
}
