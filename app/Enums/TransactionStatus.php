<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case VERIFIED = 'verified';
    case FAILED = 'failed';
    case VOIDED = 'voided';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::COMPLETED => 'Completed',
            self::VERIFIED => 'Verified',
            self::FAILED => 'Failed',
            self::VOIDED => 'Voided',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::COMPLETED => 'success',
            self::VERIFIED => 'primary',
            self::FAILED => 'danger',
            self::VOIDED => 'secondary',
            self::REFUNDED => 'info',
        };
    }

    public function isSuccessful(): bool
    {
        return in_array($this, [self::COMPLETED, self::VERIFIED]);
    }
}
