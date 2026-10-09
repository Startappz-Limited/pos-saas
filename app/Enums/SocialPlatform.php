<?php

namespace App\Enums;

enum SocialPlatform: string
{
    case FACEBOOK = 'facebook';
    case INSTAGRAM = 'instagram';
    case META_ADS = 'meta_ads';
    case GOOGLE_ADS = 'google_ads';

    public function label(): string
    {
        return match ($this) {
            self::FACEBOOK => 'Facebook Page',
            self::INSTAGRAM => 'Instagram Business',
            self::META_ADS => 'Meta Ads (Facebook/Instagram)',
            self::GOOGLE_ADS => 'Google Ads',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FACEBOOK => 'mdi:facebook',
            self::INSTAGRAM => 'mdi:instagram',
            self::META_ADS => 'mdi:meta',
            self::GOOGLE_ADS => 'mdi:google-ads',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FACEBOOK => 'primary',
            self::INSTAGRAM => 'danger',
            self::META_ADS => 'info',
            self::GOOGLE_ADS => 'warning',
        };
    }

    public function isOrganic(): bool
    {
        return in_array($this, [self::FACEBOOK, self::INSTAGRAM]);
    }

    public function isPaid(): bool
    {
        return in_array($this, [self::META_ADS, self::GOOGLE_ADS]);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn(self $case) => [$case->value => $case->label()])
            ->all();
    }
}
