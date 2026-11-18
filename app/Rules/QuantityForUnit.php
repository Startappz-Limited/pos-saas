<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A quantity entered in a unit of a given precision (stage 11, step 1): more
 * than 0, and no more decimals than the unit allows (0 for a unit like
 * "tablet", up to 3 for one like "kg"). Checked with `bcmath`, never a float
 * comparison, so a value with one extra decimal digit ("1.2505" against a
 * precision of 3) is refused rather than silently rounded.
 */
class QuantityForUnit implements ValidationRule
{
    public function __construct(private readonly int $precision) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // The messages name no attribute: the callback receives the text as-is, so a `:attribute`
        // placeholder would reach the user unfilled.
        // Plain decimal notation only: BCMath throws on exponents ("1e0") and on surrounding
        // whitespace, which is_numeric() accepts, so those are refused here instead of erroring later.
        if (! is_numeric($value) || preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', (string) $value) !== 1) {
            $fail(__('Enter a number.'));

            return;
        }

        $value = (string) $value;

        if (bccomp($value, '0', max($this->precision, 10)) <= 0) {
            $fail(__('Enter a number greater than 0.'));

            return;
        }

        $truncated = bcadd($value, '0', $this->precision);
        if (bccomp($value, $truncated, 10) !== 0) {
            $fail($this->precision === 0
                ? __('Enter a whole number.')
                : __('Use at most :precision decimal place(s).', ['precision' => $this->precision]));
        }
    }
}
