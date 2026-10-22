<?php

namespace App\Support\Signup;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * The email verification link (Phase 6): signed, valid 48 hours, and only the
 * newest one works. Each link carries a nonce; sending a new link replaces the
 * stored nonce, so an older link stops working, and verifying clears it so a
 * link cannot be used twice.
 */
class VerificationLink
{
    public const HOURS_VALID = 48;

    public static function url(User $user): string
    {
        $nonce = Str::random(32);
        Cache::put(self::key($user), $nonce, now()->addHours(self::HOURS_VALID + 1));

        return URL::temporarySignedRoute('verification.verify', now()->addHours(self::HOURS_VALID), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
            'n' => $nonce,
        ]);
    }

    /**
     * Whether $nonce is the newest link's. A user with no stored nonce (links
     * sent before this existed) is accepted on the signature alone.
     */
    public static function isCurrent(User $user, ?string $nonce): bool
    {
        $stored = Cache::get(self::key($user));

        return $stored === null || hash_equals((string) $stored, (string) $nonce);
    }

    public static function forget(User $user): void
    {
        Cache::forget(self::key($user));
    }

    private static function key(User $user): string
    {
        return 'verification-nonce.'.$user->getKey();
    }
}
