<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A till PIN (Phase 7): exactly 4 digits (decided 26 October 2026), and not
 * one that is guessed first: the same digit repeated, a run up or down, or a
 * common PIN. The till checks it offline against a hash it holds and locks a
 * person out after 4 wrong tries, so a guessable PIN is the weak point.
 */
class ValidTillPin implements ValidationRule
{
    /** Among the most used PINs, beyond repeats and runs. */
    private const COMMON = [
        '1212', '1122', '1313', '2000', '2580', '0852', '1004', '6969', '2468', '1357',
        '0007', '1010', '2020', '1984', '1990', '2001', '2002', '2003', '1999', '1980',
        '1100', '0101', '7777', '1221', '2112', '5555', '0011', '1001', '4545', '5150',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pin = (string) $value;

        if (! preg_match('/^\d{4}$/', $pin)) {
            $fail(__('The till PIN must be exactly 4 digits.'));

            return;
        }

        if ($this->isTrivial($pin) || in_array($pin, self::COMMON, true)) {
            $fail(__('That PIN is too easy to guess. Avoid repeated digits, runs like 1234 and common PINs.'));
        }
    }

    private function isTrivial(string $pin): bool
    {
        if (count(array_unique(str_split($pin))) === 1) {
            return true;
        }

        $digits = array_map('intval', str_split($pin));
        $up = $down = true;
        for ($i = 1; $i < count($digits); $i++) {
            $up = $up && $digits[$i] === ($digits[$i - 1] + 1) % 10;
            $down = $down && $digits[$i] === ($digits[$i - 1] + 9) % 10;
        }

        return $up || $down;
    }
}
