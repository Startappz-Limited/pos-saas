<?php

namespace App\Enums;

enum CreditAccountStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::ACTIVE => 'Active',
            self::SUSPENDED => 'Suspended',
            self::CLOSED => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACTIVE => 'success',
            self::SUSPENDED => 'danger',
            self::CLOSED => 'secondary',
        };
    }

    public function canMakePurchases(): bool
    {
        return $this === self::ACTIVE;
    }
}
