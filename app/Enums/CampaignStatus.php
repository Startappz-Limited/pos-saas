<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SCHEDULED => 'info',
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::COMPLETED => 'primary',
            self::CANCELLED => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED]);
    }
}
