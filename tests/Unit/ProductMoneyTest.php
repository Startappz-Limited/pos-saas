<?php

use App\Models\Product;

/*
 * Product profit and margin follow the money rules sales use (stage 11, step 9): to the cent, with
 * no float drift.
 */

it('gives profit to the cent', function () {
    $product = (new Product)->forceFill(['selling_price' => '100.10', 'cost_price' => '40.05']);

    expect($product->profit)->toBe(60.05);
});

it('gives margin to two decimals', function () {
    $product = (new Product)->forceFill(['selling_price' => '100.10', 'cost_price' => '40.05']);

    expect($product->profit_margin)->toBe(59.99);
});

it('gives no margin on a price of zero', function () {
    $product = (new Product)->forceFill(['selling_price' => '0', 'cost_price' => '5']);

    expect($product->profit_margin)->toBe(0.0);
});
