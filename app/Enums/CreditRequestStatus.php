<?php

namespace App\Enums;

enum CreditRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PARTIALLY_APPROVED = 'partially_approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::PARTIALLY_APPROVED => 'Partially Approved',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::PARTIALLY_APPROVED => 'info',
            self::REJECTED => 'danger',
            self::CANCELLED => 'secondary',
        };
    }
}
