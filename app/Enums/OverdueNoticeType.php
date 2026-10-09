<?php

namespace App\Enums;

enum OverdueNoticeType: string
{
    case REMINDER = 'reminder';
    case WARNING = 'warning';
    case FINAL_NOTICE = 'final_notice';
    case SUSPENSION = 'suspension';

    public function label(): string
    {
        return match ($this) {
            self::REMINDER => 'Payment Reminder',
            self::WARNING => 'Overdue Warning',
            self::FINAL_NOTICE => 'Final Notice',
            self::SUSPENSION => 'Account Suspension',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::REMINDER => 1,
            self::WARNING => 2,
            self::FINAL_NOTICE => 3,
            self::SUSPENSION => 4,
        };
    }
}
