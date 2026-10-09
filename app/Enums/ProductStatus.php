<?php

namespace App\Enums;

enum ProductStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case OUT_OF_STOCK = 'out_of_stock';
    case DISCONTINUED = 'discontinued';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::OUT_OF_STOCK => 'Out of Stock',
            self::DISCONTINUED => 'Discontinued',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::OUT_OF_STOCK => 'warning',
            self::DISCONTINUED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'x-circle',
            self::OUT_OF_STOCK => 'alert-circle',
            self::DISCONTINUED => 'slash',
        };
    }

    public function canBeSold(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function default(): self
    {
        return self::ACTIVE;
    }
}
