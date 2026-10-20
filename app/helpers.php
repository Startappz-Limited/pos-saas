<?php

use App\Support\Currency;
use App\Support\Tenancy\TenantContext;

if (! function_exists('current_business')) {
    /**
     * The business this request, job or command acts for, if one is set
     * (never throws: receipts and PDFs also render with none, e.g. in tests).
     */
    function current_business(): ?\App\Models\Business
    {
        $tenant = app(TenantContext::class);

        return $tenant->has() ? $tenant->business() : null;
    }
}

if (! function_exists('currency_code')) {
    /**
     * The current business's currency (decision 4.9: never hard-coded), else
     * the platform default.
     */
    function currency_code(): string
    {
        return current_business()?->currency ?: config('app.currency_code', 'KES');
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * The symbol of the current business's currency.
     */
    function currency_symbol(): string
    {
        return Currency::symbol(currency_code());
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency with the current business's symbol.
     */
    function format_currency(float|int|null $amount, int $decimals = 2): string
    {
        return currency_symbol().number_format($amount ?? 0, $decimals);
    }
}

if (! function_exists('business_name')) {
    /**
     * The name receipts and PDFs carry: the current business's, else the
     * platform's (stage 3 replaces config('app.name') there).
     */
    function business_name(): string
    {
        return current_business()?->name ?: (string) config('app.name');
    }
}
