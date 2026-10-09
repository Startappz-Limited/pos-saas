<?php

if (! function_exists('currency_symbol')) {
    /**
     * Get the configured currency symbol.
     */
    function currency_symbol(): string
    {
        return config('app.currency_symbol', 'KSh');
    }
}

if (! function_exists('currency_code')) {
    /**
     * Get the configured currency code.
     */
    function currency_code(): string
    {
        return config('app.currency_code', 'KES');
    }
}

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency with the configured symbol.
     */
    function format_currency(float|int|null $amount, int $decimals = 2): string
    {
        return currency_symbol() . number_format($amount ?? 0, $decimals);
    }
}
