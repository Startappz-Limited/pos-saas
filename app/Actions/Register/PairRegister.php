<?php

namespace App\Actions\Register;

use App\Models\Register;
use App\Models\RegisterPairing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

/**
 * Pairs a till device to a register (Phase 7, decision 4.5) and signs the
 * pairing user in on it with a token bound to the business and the register.
 *
 * - First pairing: the register takes the device.
 * - The same device again (an app restart, a lost token): a new token, nothing
 *   else changes.
 * - Another device (a reinstall or a replacement): the register gets a new
 *   code, the previous device's tokens are revoked and its pairing ended, so
 *   invoice numbers the old install may still hold can't repeat (4.9). The
 *   series counts on by register, so numbering carries on from the last number
 *   the server has seen.
 */
class PairRegister
{
    /**
     * @return array{register: Register, token: NewAccessToken, repaired: bool, previous_code: ?string}
     */
    public function execute(Register $register, User $user, string $deviceId, ?string $deviceName, int $businessId): array
    {
        return DB::transaction(function () use ($register, $user, $deviceId, $deviceName, $businessId): array {
            // Two managers pairing the same till at once queue here.
            $locked = Register::query()->whereKey($register->id)->lockForUpdate()->firstOrFail();
            $repaired = false;
            $previousCode = null;

            if ($locked->device_id !== null && $locked->device_id !== $deviceId) {
                $repaired = true;
                $previousCode = $locked->code;

                // Saved one by one so each ending is in the audit trail.
                RegisterPairing::query()->where('register_id', $locked->id)->whereNull('ended_at')->get()
                    ->each(fn (RegisterPairing $pairing) => $pairing->update(['ended_at' => now(), 'end_reason' => RegisterPairing::END_REPAIRED]));
                DB::table('personal_access_tokens')->where('register_id', $locked->id)->delete();

                $locked->code = $this->nextCode($locked->shop_id);
            }

            if ($locked->device_id !== $deviceId) {
                $locked->forceFill(['device_id' => $deviceId, 'paired_at' => now()])->save();
            }

            $open = RegisterPairing::query()->where('register_id', $locked->id)->whereNull('ended_at')->exists();
            if (! $open) {
                RegisterPairing::create([
                    'business_id' => $businessId,
                    'shop_id' => $locked->shop_id,
                    'register_id' => $locked->id,
                    'device_id' => $deviceId,
                    'device_name' => $deviceName,
                    'code' => $locked->code,
                    'paired_by' => $user->id,
                    'paired_at' => now(),
                ]);
            }

            $token = self::tokenFor($user, $businessId, $locked, $deviceName ?? $locked->name);

            return ['register' => $locked, 'token' => $token, 'repaired' => $repaired, 'previous_code' => $previousCode];
        });
    }

    /**
     * A token for $user bound to the business and to $register (its shop only).
     */
    public static function tokenFor(User $user, int $businessId, Register $register, string $name): NewAccessToken
    {
        $token = $user->createToken($name);
        $token->accessToken->forceFill(['business_id' => $businessId, 'register_id' => $register->id])->save();

        return $token;
    }

    /**
     * The next unused code in the shop, `R{n}`: past every code its registers
     * have and every code a pairing ever held, so an old code is never reused.
     */
    private function nextCode(int $shopId): string
    {
        $codes = DB::table('registers')->where('shop_id', $shopId)->pluck('code')
            ->merge(DB::table('register_pairings')->where('shop_id', $shopId)->pluck('code'));

        $highest = $codes
            ->map(fn (string $code): int => preg_match('/^R(\d+)$/', $code, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return 'R'.($highest + 1);
    }
}
