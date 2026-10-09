<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Renders a stored audit value for display.
 *
 * Audit payloads are free-form JSON, so a field can hold a scalar, null, a bool,
 * or a nested array. This keeps that formatting in one place instead of spreading
 * type checks through the Blade views.
 */
class AuditValue
{
    public static function render(mixed $value, int $limit = 120): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return Str::limit((string) json_encode($value), $limit);
        }

        if ($value === '') {
            return '(empty)';
        }

        return Str::limit((string) $value, $limit);
    }
}
