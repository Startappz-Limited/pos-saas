<?php

namespace App\Enums;

enum BusinessExportStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Preparing',
            self::READY => 'Ready',
            self::FAILED => 'Failed',
            self::EXPIRED => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'info',
            self::READY => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'secondary',
        };
    }
}
