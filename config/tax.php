<?php

use App\Enums\TaxClass;

return [

    /*
    |--------------------------------------------------------------------------
    | VAT engine
    |--------------------------------------------------------------------------
    |
    | Master switch. When disabled the sale controllers fall back to the legacy
    | behaviour of storing whatever `tax_amount` the client posted. When enabled
    | the server computes VAT per line and ignores the client's figure — but only
    | for shops that are actually flagged VAT registered (`shops.vat_registered`),
    | so turning this on does not silently start charging VAT everywhere.
    |
    */

    'enabled' => env('TAX_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Jurisdiction
    |--------------------------------------------------------------------------
    |
    | Kenya: VAT Act 2013. The standard rate is 16%, with a reduced 8% rate
    | retained for petroleum products. `authority` is only used for labelling on
    | invoices and reports.
    |
    */

    'country' => env('TAX_COUNTRY', 'KE'),
    'authority' => env('TAX_AUTHORITY', 'KRA'),
    'label' => env('TAX_LABEL', 'VAT'),

    /*
    |--------------------------------------------------------------------------
    | Rates (percentages)
    |--------------------------------------------------------------------------
    */

    'rates' => [
        TaxClass::Standard->value => (float) env('TAX_RATE_STANDARD', 16),
        TaxClass::Reduced->value => (float) env('TAX_RATE_REDUCED', 8),
        TaxClass::ZeroRated->value => 0.0,
        TaxClass::Exempt->value => 0.0,
        TaxClass::NonVat->value => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing convention
    |--------------------------------------------------------------------------
    |
    | Kenyan retail quotes VAT-inclusive shelf prices — the customer pays the
    | ticket price and the VAT is extracted from it. Set to false for a business
    | that quotes net prices and adds VAT on top (common in B2B/wholesale).
    | Overridable per shop via `Shop.settings['tax']['prices_include_tax']`.
    |
    */

    'prices_include_tax' => (bool) env('TAX_PRICES_INCLUDE_TAX', true),

    /*
    |--------------------------------------------------------------------------
    | Default tax class
    |--------------------------------------------------------------------------
    |
    | Applied to any product that has no explicit `tax_class`. Overridable per
    | shop via `Shop.settings['tax']['default_class']`.
    |
    */

    'default_class' => env('TAX_DEFAULT_CLASS', TaxClass::Standard->value),

    /*
    |--------------------------------------------------------------------------
    | Are the sale-level fees taxable?
    |--------------------------------------------------------------------------
    |
    | Delivery, packaging and other charges recharged to the customer. Strictly,
    | a VAT-registered supplier delivering standard-rated goods must charge VAT
    | on the delivery too. It is left OFF by default because switching it on
    | changes what customers are billed, and the correct treatment depends on
    | what the business actually recharges. Turn it on deliberately.
    |
    */

    'fees_taxable' => (bool) env('TAX_FEES_TAXABLE', false),

    'fees_tax_class' => env('TAX_FEES_TAX_CLASS', TaxClass::Standard->value),

];
