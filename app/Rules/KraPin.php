<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Kenya Revenue Authority Personal Identification Number.
 *
 * The format is one letter, nine digits and a check letter — "P051234567X" for a
 * company or partnership, "A" for an individual. Validating the shape at entry
 * stops a mistyped PIN reaching a tax invoice, where it is both a compliance
 * problem for the seller and a lost input-VAT claim for the buyer.
 *
 * This checks the format only; it cannot confirm the PIN is registered. That
 * needs KRA's PIN checker.
 */
class KraPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! preg_match('/^[A-Za-z]\d{9}[A-Za-z]$/', trim($value))) {
            $fail('The :attribute must be a valid KRA PIN, for example P051234567X.');
        }
    }
}
