<?php

namespace App\Support\Stock;

use App\Casts\Quantity;
use App\Models\ProductVariation;
use App\Rules\QuantityForUnit;

/**
 * Checks an intake's quantities (stage 11, review follow-up): each is in its variation's base unit, so a
 * whole-unit product refuses a fraction and a weight product takes its three decimals, and accepted plus
 * rejected may not exceed received. The create request, the edit request and the completion all use it,
 * so an edit can't put a figure on the intake that creating it would have refused.
 */
class IntakeQuantities
{
    public const FIELDS = ['quantity_received', 'quantity_accepted', 'quantity_rejected'];

    /**
     * Messages keyed by field. A zero accepted or rejected figure is fine (nothing accepted or rejected), so
     * only non-zero figures are checked against the precision.
     *
     * @param  array<string, mixed>  $values  the intake's three quantities, as they will be stored
     * @param  bool  $checkTotal  whether accepted + rejected must fit within received (the requests); completion checks precision only
     * @return array<string, list<string>>
     */
    public static function errors(?ProductVariation $variation, array $values, bool $checkTotal = true): array
    {
        $precision = (int) ($variation?->baseUnit?->precision ?? 3);
        $errors = [];

        foreach (self::FIELDS as $field) {
            $value = $values[$field] ?? null;

            if (! is_numeric($value) || (float) $value === 0.0) {
                continue;
            }

            (new QuantityForUnit($precision))->validate($field, (string) $value, function (string $message) use (&$errors, $field): void {
                $errors[$field][] = $message;
            });
        }

        if ($checkTotal && is_numeric($values['quantity_received'] ?? null)) {
            $handled = bcadd(
                Quantity::normalize(is_numeric($values['quantity_accepted'] ?? null) ? $values['quantity_accepted'] : 0),
                Quantity::normalize(is_numeric($values['quantity_rejected'] ?? null) ? $values['quantity_rejected'] : 0),
                3
            );

            if (bccomp($handled, Quantity::normalize($values['quantity_received']), 3) > 0) {
                $errors['quantity_accepted'][] = __('Accepted + rejected quantities cannot exceed the received quantity.');
            }
        }

        return $errors;
    }
}
