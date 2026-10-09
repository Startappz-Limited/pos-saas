<?php

namespace App\Enums;

enum CustomerType: string
{
    case RETAIL = 'retail';
    case WHOLESALE = 'wholesale';
    case VIP = 'vip';

    public function label(): string
    {
        return match ($this) {
            self::RETAIL => 'Retail Customer',
            self::WHOLESALE => 'Wholesale Customer',
            self::VIP => 'VIP Customer',
        };
    }

    public function defaultSaleType(): SaleType
    {
        return match ($this) {
            self::RETAIL, self::VIP => SaleType::RETAIL,
            self::WHOLESALE => SaleType::WHOLESALE,
        };
    }
}
