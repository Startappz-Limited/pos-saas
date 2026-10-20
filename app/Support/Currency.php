<?php

namespace App\Support;

/**
 * Display symbols for the currencies a business may choose (decision 4.9:
 * currency is per business). A code with no symbol here shows as its code.
 */
final class Currency
{
    /** @var array<string, string> */
    private const SYMBOLS = [
        'KES' => 'KSh',
        'UGX' => 'USh',
        'TZS' => 'TSh',
        'RWF' => 'RF',
        'ETB' => 'Br',
        'NGN' => '₦',
        'GHS' => 'GH₵',
        'ZAR' => 'R',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
    ];

    public static function symbol(string $code): string
    {
        $code = strtoupper($code);

        // The platform's configured symbol stays for its own default currency.
        if ($code === strtoupper((string) config('app.currency_code', 'KES')) && config('app.currency_symbol')) {
            return (string) config('app.currency_symbol');
        }

        return self::SYMBOLS[$code] ?? $code.' ';
    }

    /**
     * @return array<string, string> code => "CODE (symbol)", for a select
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::SYMBOLS as $code => $symbol) {
            $options[$code] = "{$code} ({$symbol})";
        }

        return $options;
    }
}
