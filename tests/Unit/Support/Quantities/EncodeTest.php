<?php

use App\Support\Quantities\Encode;

/*
 * The three encodings every API quantity goes through (stage 11, step 8).
 */

it('gives a JSON number: an int when whole, a float only when fractional', function (mixed $value, int|float|null $expected) {
    expect(Encode::flat($value))->toBe($expected);
})->with([
    ['2.000', 2],
    ['2', 2],
    [2, 2],
    ['2.500', 2.5],
    ['0.001', 0.001],
    ['0.000', 0],
    ['-3.000', -3],
    [null, null],
]);

it('gives a whole number rounded down for the pull\'s integer fields', function (mixed $value, ?int $expected) {
    expect(Encode::pullWhole($value))->toBe($expected);
})->with([
    ['2.500', 2],
    ['2.999', 2],
    ['2.000', 2],
    ['0.999', 0],
    [null, null],
]);

it('gives an exact string to the requested decimals, truncated and never rounded up', function (mixed $value, int $precision, ?string $expected) {
    expect(Encode::exact($value, $precision))->toBe($expected);
})->with([
    ['2.000', 3, '2.000'],
    ['2.5', 3, '2.500'],
    ['2.9999', 3, '2.999'],
    ['2.5', 0, '2'],
    ['2.9', 0, '2'],
    ['0.5', 2, '0.50'],
    ['-2.5', 3, '-2.500'],
    ['0', 3, '0.000'],
    [null, 3, null],
]);

it('never adds float noise to an exact figure', function () {
    // 0.1 + 0.2 in binary floats is 0.30000000000000004; the exact string must read 0.300.
    expect(Encode::exact('0.300', 3))->toBe('0.300')
        ->and(Encode::flat('0.300'))->toBe(0.3);
});
