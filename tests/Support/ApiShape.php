<?php

namespace Tests\Support;

/**
 * JSON response shapes for the API contract tests (flat `/api` and
 * `/api/v1`): a response is reduced to its keys and value types, and a later
 * response may add keys but never lose one or change its type.
 */
final class ApiShape
{
    /**
     * The value's shape: types instead of values; lists by their first element.
     */
    public static function of(mixed $value): mixed
    {
        // JSON has one number type: 0 encodes as an int and 12.5 as a float, and
        // the app reads both as numbers.
        if (is_int($value) || is_float($value)) {
            return 'number';
        }

        if (! is_array($value)) {
            return get_debug_type($value);
        }

        if (array_is_list($value)) {
            return $value === [] ? ['(empty list)'] : [self::of($value[0])];
        }

        $shape = [];
        foreach ($value as $key => $item) {
            $shape[$key] = self::of($item);
        }

        return $shape;
    }

    /**
     * Where $actual breaks the recorded $expected shape: a key gone, a type
     * changed, a list turned into something else. New keys are fine.
     *
     * @return list<string>
     */
    public static function breaks(mixed $expected, mixed $actual, string $path = '$'): array
    {
        // null is compatible with any type: whether a field is empty depends on
        // the data (an empty report, a source without an icon), not the contract.
        if ($expected === 'null' || $actual === 'null') {
            return [];
        }

        if (is_string($expected)) {
            return $expected === $actual ? [] : ["{$path}: was {$expected}, now ".json_encode($actual)];
        }

        if (array_is_list($expected)) {
            if (! is_array($actual) || ! array_is_list($actual)) {
                return ["{$path}: was a list, now ".json_encode($actual)];
            }
            if ($expected === ['(empty list)'] || $actual === ['(empty list)']) {
                return [];
            }

            return self::breaks($expected[0], $actual[0], "{$path}[0]");
        }

        if (! is_array($actual) || array_is_list($actual)) {
            return ["{$path}: was an object, now ".json_encode($actual)];
        }

        $breaks = [];
        foreach ($expected as $key => $shape) {
            $breaks = array_key_exists($key, $actual)
                ? [...$breaks, ...self::breaks($shape, $actual[$key], "{$path}.{$key}")]
                : [...$breaks, "{$path}.{$key}: removed"];
        }

        return $breaks;
    }
}
