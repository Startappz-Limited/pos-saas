<?php

namespace App\Enums;

enum PricingType: string
{
    case REGULAR = 'regular';
    case MEMBER = 'member';
    case BULK = 'bulk';
    case PROMOTIONAL = 'promotional';
    case CUSTOMER_SPECIFIC = 'customer_specific';
    case TIME_BASED = 'time_based';

    public function label(): string
    {
        return match ($this) {
            self::REGULAR => 'Regular Price',
            self::MEMBER => 'Member Price',
            self::BULK => 'Bulk Pricing',
            self::PROMOTIONAL => 'Promotional Price',
            self::CUSTOMER_SPECIFIC => 'Customer Specific',
            self::TIME_BASED => 'Time-Based Price',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::REGULAR => 'Standard pricing for all customers',
            self::MEMBER => 'Special pricing for members',
            self::BULK => 'Discounted pricing for bulk purchases',
            self::PROMOTIONAL => 'Temporary promotional pricing',
            self::CUSTOMER_SPECIFIC => 'Custom pricing for specific customers',
            self::TIME_BASED => 'Pricing varies by time period',
        };
    }

    public function requiresDateRange(): bool
    {
        return in_array($this, [self::PROMOTIONAL, self::TIME_BASED]);
    }

    public function requiresMinQuantity(): bool
    {
        return $this === self::BULK;
    }

    public function requiresCustomer(): bool
    {
        return $this === self::CUSTOMER_SPECIFIC;
    }
}
