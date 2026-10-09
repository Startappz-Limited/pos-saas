<?php

namespace App\Enums;

enum SaleStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::VOIDED => 'Voided',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::VOIDED => 'danger',
            self::REFUNDED => 'info',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    public function canVoid(): bool
    {
        return in_array($this, [self::PENDING, self::COMPLETED]);
    }
}
