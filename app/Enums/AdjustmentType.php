<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';
    // Some lines up, some down (a recount), since lines are signed (stage 10, step 1g).
    case MIXED = 'mixed';

    /**
     * Derived from an adjustment's signed lines.
     *
     * @param  iterable<int, int>  $changes
     */
    public static function fromChanges(iterable $changes): self
    {
        $changes = collect($changes);

        return match (true) {
            $changes->every(fn (int $change): bool => $change > 0) => self::INCREASE,
            $changes->every(fn (int $change): bool => $change < 0) => self::DECREASE,
            default => self::MIXED,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::INCREASE => 'Stock Increase',
            self::DECREASE => 'Stock Decrease',
            self::MIXED => 'Increases and Decreases',
        };
    }

    public function multiplier(): int
    {
        return match ($this) {
            self::INCREASE => 1,
            self::DECREASE => -1,
            self::MIXED => 1,
        };
    }
}
