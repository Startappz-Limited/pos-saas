<?php

namespace App\Enums;

/**
 * An intake's quality status, derived from its quantities, never chosen (decided
 * 5 November 2026): nothing rejected is passed, some of each is partial, nothing
 * accepted is failed. StockIntake sets it on every save.
 */
enum StockIntakeQualityStatus: string
{
    case PASSED = 'passed';
    case PARTIAL = 'partial';
    case FAILED = 'failed';

    public static function fromQuantities(float|int|string|null $accepted, float|int|string|null $rejected): self
    {
        if ((float) $rejected <= 0) {
            return self::PASSED;
        }

        return (float) $accepted > 0 ? self::PARTIAL : self::FAILED;
    }

    public function label(): string
    {
        return match ($this) {
            self::PASSED => __('Passed'),
            self::PARTIAL => __('Partial'),
            self::FAILED => __('Failed'),
        };
    }

    /**
     * The theme's semantic colour for this status's badge.
     */
    public function color(): string
    {
        return match ($this) {
            self::PASSED => 'success',
            self::PARTIAL => 'warning',
            self::FAILED => 'danger',
        };
    }
}
