<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNPAID => 'danger',
            self::PARTIAL => 'warning',
            self::PAID => 'success',
            self::REFUNDED => 'info',
        };
    }
}
