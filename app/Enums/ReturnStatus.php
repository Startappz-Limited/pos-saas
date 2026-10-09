<?php

namespace App\Enums;

enum ReturnStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECEIVED = 'received';
    case INSPECTED = 'inspected';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::RECEIVED => 'Items Received',
            self::INSPECTED => 'Inspected',
            self::COMPLETED => 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::APPROVED => 'info',
            self::REJECTED => 'danger',
            self::RECEIVED => 'primary',
            self::INSPECTED => 'secondary',
            self::COMPLETED => 'success',
        };
    }

    public function canEdit(): bool
    {
        return $this === self::PENDING;
    }

    public function canRefund(): bool
    {
        return in_array($this, [
            self::RECEIVED,
            self::INSPECTED,
            self::COMPLETED,
        ]);
    }
}
