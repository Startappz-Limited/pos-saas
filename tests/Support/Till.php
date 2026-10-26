<?php

namespace Tests\Support;

use App\Models\Register;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Testing\TestResponse;

/**
 * A paired till for `/api/v1` tests (Phase 7): sign in, pair a device to a
 * register, and clear what one request leaves behind before the next.
 */
final class Till
{
    /**
     * Clears what the previous request left behind. The business goes first:
     * forgetting it reads the signed-in user, which would otherwise rebuild
     * the guard around the previous request and its token.
     */
    public static function freshRequest(): void
    {
        app(TenantContext::class)->forget();
        app('auth')->forgetGuards();
    }

    /**
     * A `/api/v1` token for $user (password "password"), with no register.
     */
    public static function signIn(User $user): string
    {
        self::freshRequest();

        return test()->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Phone'])
            ->assertOk()->json('data.token');
    }

    public static function pair(string $token, Register $register, string $deviceId = 'device-1'): TestResponse
    {
        self::freshRequest();

        return test()->withToken($token)->postJson("/api/v1/registers/{$register->uuid}/pair", ['device_id' => $deviceId, 'device_name' => 'Front till']);
    }

    /**
     * A token bound to $register, paired by $user.
     */
    public static function token(User $user, Register $register, string $deviceId = 'device-1'): string
    {
        return self::pair(self::signIn($user), $register, $deviceId)->assertOk()->json('data.token');
    }
}
