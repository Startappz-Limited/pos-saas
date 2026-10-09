<?php

namespace App\Enums;

/**
 * Where an abandoned website cart stands from the POS's point of view.
 *
 * `abandoned`, `recovered` come from the website; `contacted`, `converted` and
 * `lost` are staff decisions that a later webhook must never overwrite.
 */
enum AbandonedCartStatus: string
{
    case Abandoned = 'abandoned';
    case Contacted = 'contacted';
    case Recovered = 'recovered';
    case Converted = 'converted';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Abandoned => 'Abandoned',
            self::Contacted => 'Contacted',
            self::Recovered => 'Recovered online',
            self::Converted => 'Converted to sale',
            self::Lost => 'Lost',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Abandoned => 'warning',
            self::Contacted => 'info',
            self::Recovered => 'success',
            self::Converted => 'primary',
            self::Lost => 'secondary',
        };
    }

    /**
     * Statuses a member of staff may set by hand. `converted` only comes from a
     * real sale and `recovered` only from the website.
     *
     * @return array<int, self>
     */
    public static function manual(): array
    {
        return [self::Abandoned, self::Contacted, self::Lost];
    }

    /**
     * A converted cart is closed: it has a sale behind it.
     */
    public function isFinal(): bool
    {
        return $this === self::Converted;
    }
}
