<?php

namespace App\Enums;

enum SaleType: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::RETAIL => 'Retail',
            self::WHOLESALE => 'Wholesale',
        };
    }

    public function priceField(): string
    {
        return match ($this) {
            self::RETAIL => 'selling_price',
            self::WHOLESALE => 'wholesale_price',
        };
    }
}
