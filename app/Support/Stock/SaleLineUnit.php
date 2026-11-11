<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
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
        $baseQuantity = isset($item['base_quantity']) && $item['base_quantity'] !== null && $item['base_quantity'] !== ''
            ? Quantity::normalize($item['base_quantity'])
            : bcmul($quantity, (string) ($unit?->factor ?? '1'), 3);

        return ['unit' => $unit, 'quantity' => $quantity, 'base_quantity' => $baseQuantity];
    }
}
