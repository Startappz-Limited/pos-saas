<?php

namespace App\Enums;

enum AlertStatus: string
{
    case PENDING = 'pending';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';
    case IGNORED = 'ignored';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::RESOLVED => 'Resolved',
            self::IGNORED => 'Ignored',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'danger',
            self::ACKNOWLEDGED => 'warning',
            self::RESOLVED => 'success',
            self::IGNORED => 'secondary',
        };
    }
}
