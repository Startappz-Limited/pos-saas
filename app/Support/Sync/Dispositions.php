<?php

namespace App\Support\Sync;

/**
 * What a till does with a push result that isn't `applied` or `duplicate`
 * (stage 10, step 5c; the table in the stage file's "Contract"): `retry`
 * sends it again with the next push, `drop` removes it and shows why, `hold`
 * keeps it, marked, until a manager accepts or discards it. Every code has
 * exactly one class; an unknown code is held, never resent in a loop.
 */
final class Dispositions
{
    public const RETRY = 'retry';

    public const DROP = 'drop';

    public const HOLD = 'hold';

    private const BY_CODE = [
        'customer_not_synced' => self::RETRY,
        'sale_not_synced' => self::RETRY,
        'cash_session_not_synced' => self::RETRY,
        'subscription_inactive' => self::RETRY,
        'server_error' => self::RETRY,
        'void_window_closed' => self::DROP,
        'sale_has_returns' => self::DROP,
        'forbidden' => self::DROP,
        'sale_already_paid' => self::DROP,
        'outside_trading_lease' => self::HOLD,
        'invalid_invoice_number' => self::HOLD,
        'invoice_number_taken' => self::HOLD,
        'validation_failed' => self::HOLD,
        'unknown_item_type' => self::HOLD,
        'unknown_unit' => self::HOLD,
    ];

    public static function for(string $status, ?string $code): ?string
    {
        return match ($status) {
            'applied', 'duplicate' => null,
            'pending', 'failed' => self::RETRY,
            default => self::BY_CODE[(string) $code] ?? self::HOLD,
        };
    }
}
