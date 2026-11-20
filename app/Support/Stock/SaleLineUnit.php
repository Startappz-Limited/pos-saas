<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductUnit;
use App\Models\ProductVariation;

/**
 * A sale or return line's unit and base quantity (stage 11, step 3): the unit
 * named on the line (by uuid, including a since-deleted one), else the
 * variation's base unit. The base quantity is `quantity x factor`, unless the
 * caller already gives one (the till's own figure, from a push — step 8
 * settles a mismatch between the two; this just prefers what's given).
 */
class SaleLineUnit
{
    /**
     * @param  array<string, mixed>  $item  a validated line: `quantity`, optional `unit_id` (uuid), optional `base_quantity`
     * @return array{unit: ?ProductUnit, quantity: string, base_quantity: string}
     */
    public static function resolve(ProductVariation $variation, array $item): array
    {
        $unit = ! empty($item['unit_id']) ? ProductUnit::resolveFor($variation, $item['unit_id']) : null;
        $unit ??= $variation->baseUnit;

        $quantity = Quantity::normalize($item['quantity']);
        // A computed base quantity must be a positive figure the ledger can hold exactly (BaseQuantity):
        // otherwise the sale would move no stock. A till's own figure is checked by its push instead.
        $baseQuantity = isset($item['base_quantity']) && $item['base_quantity'] !== null && $item['base_quantity'] !== ''
            ? Quantity::normalize($item['base_quantity'])
            : (BaseQuantity::of($quantity, Quantity::normalize($unit?->factor ?? '1'), (int) ($variation->baseUnit?->precision ?? 3)) ?? throw new BusinessRuleException(__('That quantity is too small to sell in this unit: it makes less than one base unit, or a fraction of one.')));

        return ['unit' => $unit, 'quantity' => $quantity, 'base_quantity' => $baseQuantity];
    }
}
