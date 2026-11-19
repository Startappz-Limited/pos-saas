<?php

namespace App\Support\Stock;

/**
 * Converts a quantity in a unit to base units (stage 11, review follow-up): quantity x factor, which
 * the stock ledger holds at scale 3. A product of a quantity and a factor (each at most 3 decimals)
 * can have 6 decimals, so the product is checked exactly before it is rounded: a result that is zero
 * or cannot be held at scale 3 is refused, rather than silently truncated to a figure that moves no
 * stock (0.001 of a 0.001 factor is 0.000001).
 *
 * The result must also fit the base unit's own precision: a pack with a factor of 0.5 of a whole-tablet
 * product can be sold two at a time (one tablet), never one (half a tablet).
 */
class BaseQuantity
{
    /**
     * The base quantity at scale 3, or null when it is zero or not exactly representable at the base
     * unit's precision (at most 3). The sign is kept, so a decrease converts to a negative figure.
     */
    public static function of(string $quantity, string $factor, int $basePrecision = 3): ?string
    {
        $exact = bcmul($quantity, $factor, 6);

        if (bccomp($exact, '0', 6) === 0) {
            return null;
        }

        $precision = max(0, min(3, $basePrecision));

        if (bccomp(bcadd($exact, '0', $precision), $exact, 6) !== 0) {
            return null;
        }

        return bcadd($exact, '0', 3);
    }
}
