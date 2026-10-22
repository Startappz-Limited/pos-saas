<?php

namespace App\Support\Platform;

/**
 * Whether the current web session is a platform operator impersonating a
 * business user (Phase 5, B3), and who the operator is. While it lasts the
 * signed-in user is the impersonated one, so nothing of the operator's
 * rights applies; the operator's id is kept only to end the session and to
 * mark audit rows.
 */
final class Impersonation
{
    public const OPERATOR_KEY = 'impersonation.operator_id';

    public const RECORD_KEY = 'impersonation.id';

    public const EXPIRES_KEY = 'impersonation.expires_at';

    public static function active(): bool
    {
        return self::operatorId() !== null;
    }

    /**
     * The impersonating operator's user id, or null.
     */
    public static function operatorId(): ?int
    {
        // Outside a web request (queue, console) there is no session to read.
        if (! app()->bound('session.store')) {
            return null;
        }

        $id = app('session.store')->get(self::OPERATOR_KEY);

        return $id === null ? null : (int) $id;
    }
}
