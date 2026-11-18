<?php

namespace App\Support\Stock;

/**
 * Converts a quantity in a unit to base units (stage 11, review follow-up): quantity x factor, which
 * the stock ledger holds at scale 3. A product of a quantity and a factor (each at most 3 decimals)
 * can have 6 decimals, so the product is checked exactly before it is rounded: a result that is zero
 * or cannot be held at scale 3 is refused, rather than silently truncated to a figure that moves no
 * stock (0.001 of a 0.001 factor is 0.000001).
 */
class BaseQuantity
{
    /**
     * The base quantity at scale 3, or null when it is zero or not exactly representable at scale 3.
     * The sign is kept, so a decrease converts to a negative figure.
     */
    public static function of(string $quantity, string $factor): ?string
    {
        $exact = bcmul($quantity, $factor, 6);

        if (bccomp($exact, '0', 6) === 0) {
            return null;
        }

        $rounded = bcadd($exact, '0', 3);

        return bccomp($rounded, $exact, 6) === 0 ? $rounded : null;
    }
}
