<?php

namespace App\Support\Receipts;

use App\Models\SaleItem;
use App\Support\Quantities\Encode;

/**
 * How a sale line's quantity reads on a receipt or invoice (stage 11, step 3): the quantity to the
 * unit's decimals with the unit's name, such as "2 Strip", "2.500 kg" or "1 Crate". A line with no
 * unit (sold before units) reads as its plain quantity.
 */
final class LineQuantity
{
    public static function for(SaleItem $item): string
    {
        $unit = $item->unit;
        $precision = (int) ($unit?->precision ?? 0);
        $quantity = Encode::exact($item->quantity, $precision) ?? '0';

        return $unit === null ? $quantity : "{$quantity} {$unit->name}";
    }
}
