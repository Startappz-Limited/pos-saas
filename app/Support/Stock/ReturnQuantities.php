<?php

namespace App\Support\Stock;

use App\Models\SaleItem;
use App\Rules\QuantityForUnit;

/**
 * Checks the quantities of a return's lines against the unit each line was sold in (stage 11,
 * step 5 follow-up): a whole-unit line refuses a fraction, a weight line takes its three decimals.
 * The create request and the update controller both use it, so the two stay in step.
 */
class ReturnQuantities
{
    /**
     * Messages keyed by `items.N.quantity` for each line whose quantity has more decimals than its
     * sold unit allows (or is not a positive number).
     *
     * @param  array<int|string, mixed>  $items  the return's lines: `sale_item_id` and `quantity`
     * @return array<string, list<string>>
     */
    public static function errors(array $items): array
    {
        $ids = collect($items)->pluck('sale_item_id')->filter()->values();
        $saleItems = $ids->isEmpty() ? collect() : SaleItem::query()->with(['unit', 'variation.baseUnit'])->whereIn('id', $ids)->get()->keyBy('id');

        $errors = [];
        foreach ($items as $index => $item) {
            $saleItem = $saleItems->get($item['sale_item_id'] ?? null);

            if ($saleItem === null || ! isset($item['quantity']) || ! is_numeric($item['quantity'])) {
                continue;
            }

            (new QuantityForUnit($saleItem->quantityPrecision()))->validate(
                "items.{$index}.quantity",
                $item['quantity'],
                function (string $message) use (&$errors, $index): void {
                    $errors["items.{$index}.quantity"][] = $message;
                },
            );
        }

        return $errors;
    }
}
