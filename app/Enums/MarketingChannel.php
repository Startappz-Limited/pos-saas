<?php

namespace App\Enums;

enum MarketingChannel: string
{
    case SOCIAL_MEDIA = 'social_media';
    case GOOGLE_ADS = 'google_ads';
    case EMAIL = 'email';
    case SMS = 'sms';
    case RADIO = 'radio';
    case TV = 'tv';
    case PRINT = 'print';
    case BILLBOARD = 'billboard';
    case INFLUENCER = 'influencer';
    case REFERRAL = 'referral';
    case DIRECT = 'direct';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SOCIAL_MEDIA => 'Social Media',
            self::GOOGLE_ADS => 'Google Ads',
            self::EMAIL => 'Email Marketing',
            self::SMS => 'SMS Marketing',
            self::RADIO => 'Radio',
            self::TV => 'Television',
            self::PRINT => 'Print Media',
            self::BILLBOARD => 'Billboard/Outdoor',
            self::INFLUENCER => 'Influencer Marketing',
            self::REFERRAL => 'Referral Program',
            self::DIRECT => 'Direct Marketing',
            self::OTHER => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SOCIAL_MEDIA => 'solar:users-group-two-rounded-bold',
            self::GOOGLE_ADS => 'mdi:google-ads',
            self::EMAIL => 'solar:letter-bold',
            self::SMS => 'solar:phone-bold',
            self::RADIO => 'solar:radio-bold',
            self::TV => 'solar:tv-bold',
            self::PRINT => 'solar:newspaper-bold',
            self::BILLBOARD => 'solar:map-bold',
            self::INFLUENCER => 'solar:star-bold',
            self::REFERRAL => 'solar:share-bold',
            self::DIRECT => 'solar:hand-shake-bold',
            self::OTHER => 'solar:widget-bold',
        };
    }
}
