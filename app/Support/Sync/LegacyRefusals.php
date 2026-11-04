<?php

namespace App\Support\Sync;

/**
 * Push results in Phase 7's terms for an app older than this stage (stage 10,
 * step 5c): an app that sends no `X-App-Version`, or one below
 * `config('sync.min_version.dispositions')`, gets the codes it knows
 * (`customer_not_synced` becomes `validation_failed`; `sale_not_synced` and
 * `cash_session_not_synced` become `not_found`), status `refused` for
 * `pending`, and no `disposition`. A `sale_void` or `outbox_item_discarded`
 * can't come from such an app, so its result is left as it is. Until the
 * paired app is released the minimum is unset, so every app gets these.
 */
final class LegacyRefusals
{
    private const CODES = [
        'customer_not_synced' => 'validation_failed',
        'sale_not_synced' => 'not_found',
        'cash_session_not_synced' => 'not_found',
    ];

    private const NEW_ITEM_TYPES = ['sale_void', 'outbox_item_discarded'];

    public static function knowsDispositions(?string $appVersion): bool
    {
        $minimum = config('sync.min_version.dispositions');

        return $minimum !== null && $minimum !== '' && $appVersion !== null && $appVersion !== ''
            && version_compare($appVersion, (string) $minimum, '>=');
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    public static function forApp(array $results, ?string $appVersion): array
    {
        if (self::knowsDispositions($appVersion)) {
            return $results;
        }

        return array_map(function (array $result): array {
            if (in_array($result['type'] ?? null, self::NEW_ITEM_TYPES, true)) {
                return $result;
            }

            unset($result['disposition']);
            if (isset($result['code'], self::CODES[$result['code']])) {
                $result['code'] = self::CODES[$result['code']];
            }
            if (($result['status'] ?? null) === 'pending') {
                $result['status'] = 'refused';
            }

            return $result;
        }, $results);
    }
}
