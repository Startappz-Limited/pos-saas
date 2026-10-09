<?php

namespace App\Enums;

use Carbon\Carbon;

enum RecurrenceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case BIWEEKLY = 'biweekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Daily',
            self::WEEKLY => 'Weekly',
            self::BIWEEKLY => 'Every 2 Weeks',
            self::MONTHLY => 'Monthly',
            self::QUARTERLY => 'Quarterly',
            self::YEARLY => 'Yearly',
        };
    }

    public function nextDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::DAILY => $from->copy()->addDay(),
            self::WEEKLY => $from->copy()->addWeek(),
            self::BIWEEKLY => $from->copy()->addWeeks(2),
            self::MONTHLY => $from->copy()->addMonth(),
            self::QUARTERLY => $from->copy()->addMonths(3),
            self::YEARLY => $from->copy()->addYear(),
        };
    }
}
