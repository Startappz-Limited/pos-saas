<?php

namespace App\Enums;

enum CategoryStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'x-circle',
        };
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public static function default(): self
    {
        return self::ACTIVE;
    }
}
