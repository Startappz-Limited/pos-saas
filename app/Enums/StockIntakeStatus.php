<?php

namespace App\Enums;

enum StockIntakeStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function canEdit(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS]);
    }

    public function canComplete(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS]);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::PENDING, self::IN_PROGRESS]);
    }
}
