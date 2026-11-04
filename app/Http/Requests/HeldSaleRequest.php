<?php

namespace App\Http\Requests;

/**
 * A held till sale a manager accepts "as recorded" (stage 10, step 5c): the
 * sale happened as the till rang it, so the upper limits that guard keyed-in
 * sales are lifted (`large_line`, `large_amount`), while everything that
 * would make it wrong (no items, a product not of the business, a quantity
 * below 1, negative amounts, a discount above the subtotal) still refuses it.
 * The handler drops a bad email and cuts long text first.
 */
class HeldSaleRequest extends StoreSaleRequest
{
    /**
     * The fields whose upper limit is lifted, with the flag their excess sets.
     */
    public const LIFTED = [
        'items.*.quantity' => 'large_line',
        'items.*.price' => 'large_line',
        'delivery_fee' => 'large_amount',
        'packaging_fee' => 'large_amount',
        'other_expenses' => 'large_amount',
        'tax_amount' => 'large_amount',
        'discount_amount' => 'large_amount',
    ];

    public function rules(): array
    {
        $rules = parent::rules();
        foreach (array_keys(self::LIFTED) as $field) {
            $rules[$field] = array_values(array_filter($rules[$field], fn ($rule): bool => ! (is_string($rule) && str_starts_with($rule, 'max:'))));
        }

        return $rules;
    }
}
