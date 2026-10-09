<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case BLACKLISTED = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
            self::BLACKLISTED => 'Blacklisted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::SUSPENDED => 'warning',
            self::BLACKLISTED => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ACTIVE => 'check-circle',
            self::INACTIVE => 'x-circle',
            self::SUSPENDED => 'pause-circle',
            self::BLACKLISTED => 'slash',
        };
    }

    public function canPlaceOrders(): bool
    {
        return $this === self::ACTIVE;
    }
}
