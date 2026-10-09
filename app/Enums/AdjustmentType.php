<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';

    public function label(): string
    {
        return match ($this) {
            self::INCREASE => 'Stock Increase',
            self::DECREASE => 'Stock Decrease',
        };
    }

    public function multiplier(): int
    {
        return match ($this) {
            self::INCREASE => 1,
            self::DECREASE => -1,
        };
    }
}
