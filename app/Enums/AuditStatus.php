<?php

namespace App\Enums;

/**
 * Outcome of the audited operation. Recorded so a failed or abandoned attempt is
 * as visible as a successful one — "nothing was written" and "the write failed"
 * must never look the same in an audit trail.
 */
enum AuditStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Success',
            self::FAILED => 'Failed',
            self::PENDING => 'Pending',
        };
    }

    /**
     * Bootstrap contextual colour for status badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::SUCCESS => 'success',
            self::FAILED => 'danger',
            self::PENDING => 'warning',
        };
    }
}
