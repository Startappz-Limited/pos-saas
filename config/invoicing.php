<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice numbering
    |--------------------------------------------------------------------------
    |
    | Sales used to be numbered `INV-` plus eight random characters, which is
    | neither sequential nor collision-proof on a unique column. A tax invoice in
    | Kenya must carry a serial number from a gapless sequence, so numbers are now
    | drawn from the `invoice_sequences` table.
    |
    | The series is what the sequence is keyed on, per shop. `yearly` resets the
    | counter each calendar year (and puts the year in the number); `continuous`
    | never resets.
    |
    */

    'prefix' => env('INVOICE_PREFIX', 'INV'),

    'reset' => env('INVOICE_RESET', 'yearly'), // yearly|continuous

    'pad' => (int) env('INVOICE_PAD', 6),

    'separator' => env('INVOICE_SEPARATOR', '-'),

    /*
    |--------------------------------------------------------------------------
    | Include the shop code
    |--------------------------------------------------------------------------
    |
    | With several shops issuing invoices concurrently, embedding the shop code
    | keeps each shop's series independently readable: INV-KLA-2026-000123.
    |
    */

    'include_shop_code' => (bool) env('INVOICE_INCLUDE_SHOP_CODE', true),

];
