<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A `decimal(15,3)` quantity or `decimal(15,4)` unit cost column, read and
 * written as a scale-3 (or scale-4) string (stage 11, step 1): SQLite stores
 * these columns as doubles and MySQL as exact fixed-point values, so reading
 * rounds to the column's scale (absorbing SQLite's binary-float noise, such
 * as `0.1 + 0.2` landing a few units of the 15th digit away from `0.3`)
 * rather than truncating, which could read a hair under the true value as one
 * less at the last decimal. Writing only normalises an already-clean value
 * (app input, `bcmath` arithmetic), so it truncates instead: there is no
 * engine noise to round away, and a value with more decimals than the column
 * allows has already failed `App\Rules\QuantityForUnit`. A model's own JSON
 * gives the scale-3 string too (`"2.000"`) — only an API's encoding helper
 * (step 8) turns it into a number. Arithmetic on the value belongs to the
 * caller, with `bcmath`, never float.
 */
class Quantity implements CastsAttributes
{
    public function __construct(private readonly int $scale = 3) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::normalize($value, $this->scale);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : bcadd((string) $value, '0', $this->scale);
    }

    /**
     * Rounds a raw value (a DB driver's number or string, from a model or a
     * plain `DB::table()` read) to a canonical scale-N string.
     */
    public static function normalize(int|float|string $value, int $scale = 3): string
    {
        return number_format(round((float) $value, $scale), $scale, '.', '');
    }
}
