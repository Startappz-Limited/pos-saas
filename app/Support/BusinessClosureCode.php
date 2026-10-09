<?php

namespace App\Support;

use App\Models\Business;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * The one-time code emailed to a business owner to confirm closing their
 * business. Only a hash is kept, in the cache, for MINUTES; MAX_ATTEMPTS
 * wrong guesses use it up and a new one must be requested.
 */
class BusinessClosureCode
{
    public const MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const LENGTH = 6;

    /**
     * Seconds between two codes for the same business.
     */
    public const RESEND_SECONDS = 60;

    /**
     * Creates a new code (replacing any earlier one) and returns it in plain
     * text, to be emailed.
     */
    public static function issue(Business $business): string
    {
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        Cache::put(self::key($business), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'owner_id' => $business->owner_id,
            'issued_at' => now()->timestamp,
            'expires_at' => now()->addMinutes(self::MINUTES)->timestamp,
        ], now()->addMinutes(self::MINUTES));

        return $code;
    }

    /**
     * Whether a code is waiting to be entered.
     */
    public static function pending(Business $business): bool
    {
        return self::state($business) !== null;
    }

    /**
     * Seconds until a new code may be sent (0 when it may be sent now).
     */
    public static function resendAvailableIn(Business $business): int
    {
        $issuedAt = self::state($business)['issued_at'] ?? null;

        return $issuedAt === null ? 0 : max(0, $issuedAt + self::RESEND_SECONDS - now()->timestamp);
    }

    /**
     * Checks a code. A correct code is used up; a wrong one counts as an
     * attempt, and the last allowed attempt discards the code.
     */
    public static function verify(Business $business, string $code): bool
    {
        $state = self::state($business);

        if ($state === null || $state['owner_id'] !== $business->owner_id) {
            return false;
        }

        if (Hash::check(trim($code), $state['hash'])) {
            self::clear($business);

            return true;
        }

        $state['attempts']++;

        if ($state['attempts'] >= self::MAX_ATTEMPTS) {
            self::clear($business);
        } else {
            // Keeps the original expiry: guessing does not extend the code's life
            Cache::put(self::key($business), $state, Carbon::createFromTimestamp($state['expires_at']));
        }

        return false;
    }

    public static function clear(Business $business): void
    {
        Cache::forget(self::key($business));
    }

    /**
     * @return array{hash: string, attempts: int, owner_id: int|null, issued_at: int, expires_at: int}|null
     */
    private static function state(Business $business): ?array
    {
        return Cache::get(self::key($business));
    }

    private static function key(Business $business): string
    {
        return "business-closure-code.{$business->id}";
    }
}
