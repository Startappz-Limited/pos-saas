<?php

namespace App\Enums;

enum DiscountType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentage',
            self::FIXED => 'Fixed Amount',
        };
    }

    public function calculateDiscount(float $amount, float $discountValue): float
    {
        return match ($this) {
            self::PERCENTAGE => $amount * ($discountValue / 100),
            self::FIXED => min($discountValue, $amount),
        };
    }
}
