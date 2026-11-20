<?php

use App\Models\ProductUnit;
use App\Models\SaleItem;
use App\Support\Receipts\LineQuantity;

/*
 * A line's quantity on a receipt or invoice, with its unit (stage 11, step 3).
 */

it('reads a fractional line with its unit to the unit\'s decimals', function () {
    $item = new SaleItem(['quantity' => '2.500']);
    $item->setRelation('unit', new ProductUnit(['name' => 'kg', 'precision' => 3]));

    expect(LineQuantity::for($item))->toBe('2.500 kg');
});

it('reads a whole crate as a whole count of the crate', function () {
    $item = new SaleItem(['quantity' => '1.000']);
    $item->setRelation('unit', new ProductUnit(['name' => 'Crate', 'precision' => 0]));

    expect(LineQuantity::for($item))->toBe('1 Crate');
});

it('reads a line sold before units as its plain quantity', function () {
    $item = new SaleItem(['quantity' => '3.000']);
    $item->setRelation('unit', null);

    expect(LineQuantity::for($item))->toBe('3');
});
