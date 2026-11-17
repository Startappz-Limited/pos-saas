<?php

namespace App\Support\Quantities;

/**
 * How a quantity leaves the API (stage 11, step 8). A model's own JSON gives the
 * scale-3 string ("2.000"); each API turns it into what its contract says:
 *
 *  - `flat()`: a JSON number, an int when whole (the flat API and the /api/v1
 *    receipt's `quantity`: `json_encode(2.0)` would give `2.0`, which a strict
 *    integer parser rejects);
 *  - `pullWhole()`: a whole number, rounded down (`stock.quantity`, the old
 *    integer fields: an old app shows slightly less than exists, never more);
 *  - `exact()`: a string with the given decimals (new fields: `quantity_exact`,
 *    `base_quantity`, `base_reorder_level`, `sellable_quantity`).
 *
 * Arithmetic stays in bcmath; a float appears only at the edge, in `flat()`.
 */
final class Encode
{
    /**
     * The quantity keys the flat API sends as numbers: the stock quantities read
     * through the `Quantity` cast (stage 11, step 1). Purchase-order quantities
     * are `decimal(15,2)` without that cast and stay strings, as the recorded
     * contract has them (no flat field changes type).
     */
    public const FLAT_KEYS = [
        'quantity',
        'stock_quantity',
        'reorder_level',
        'quantity_before',
        'quantity_change',
        'quantity_after',
    ];

    /**
     * A JSON number: an int when the value is whole, a float otherwise.
     */
    public static function flat(int|float|string|null $quantity): int|float|null
    {
        if ($quantity === null) {
            return null;
        }

        $value = (float) $quantity;

        return floor($value) === $value ? (int) $value : $value;
    }

    /**
     * A whole number, rounded down, never more than the stock really is.
     */
    public static function pullWhole(int|float|string|null $quantity): ?int
    {
        return $quantity === null ? null : (int) floor((float) $quantity);
    }

    /**
     * A string with exactly $precision decimals, truncated (never rounded up),
     * so the figure never claims more stock than exists.
     */
    public static function exact(int|float|string|null $quantity, int $precision = 3): ?string
    {
        if ($quantity === null) {
            return null;
        }

        $raw = (string) $quantity;
        // Work on the decimal text so no float rounding can creep in.
        $negative = str_starts_with($raw, '-');
        $raw = ltrim($raw, '-+');
        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, $precision), $precision, '0');
        $text = ($whole === '' ? '0' : $whole).($precision > 0 ? '.'.$fraction : '');

        return ($negative && trim($text, '0.') !== '' ? '-' : '').$text;
    }
}
