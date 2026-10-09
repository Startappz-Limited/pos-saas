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

if (! function_exists('theme_asset')) {
    /**
     * URL of a file in public/ with its modified time appended, so browsers
     * fetch the new copy as soon as the file changes instead of reusing a
     * cached one (the static theme files are served with no cache rules).
     */
    function theme_asset(string $path): string
    {
        $file = public_path($path);

        return asset($path).(is_file($file) ? '?v='.filemtime($file) : '');
    }
}
