<?php

namespace App\Enums;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHING = 'publishing';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::PUBLISHING => 'Publishing',
            self::PUBLISHED => 'Published',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SCHEDULED => 'info',
            self::PUBLISHING => 'warning',
            self::PUBLISHED => 'success',
            self::FAILED => 'danger',
            self::CANCELLED => 'dark',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PUBLISHED, self::CANCELLED]);
    }
}
