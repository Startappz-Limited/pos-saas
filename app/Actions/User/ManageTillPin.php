<?php

namespace App\Actions\User;

use App\Models\TillPin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Sets or resets a person's till PIN in the current business (Phase 7).
 * Through the model, so each change is in the audit trail (without the hash).
 */
class ManageTillPin
{
    public function set(User $user, string $pin): TillPin
    {
        $tillPin = TillPin::query()->firstOrNew(['user_id' => $user->id]);
        // A new PIN also ends any lockout.
        $tillPin->fill(['pin_hash' => Hash::make($pin), 'set_at' => now(), 'locked_at' => null, 'locked_on_register_id' => null])->save();

        return $tillPin;
    }

    /**
     * Removes $user's PIN: they can't sign in on an offline till until they
     * set a new one. Returns whether there was one.
     */
    public function reset(User $user): bool
    {
        $tillPin = TillPin::query()->where('user_id', $user->id)->first();

        return $tillPin !== null && (bool) $tillPin->delete();
    }

    /**
     * Locks $user out of every till of the business until someone unlocks
     * them (repeated lockouts reported by a till).
     */
    public function lock(User $user, ?int $registerId): void
    {
        TillPin::query()->where('user_id', $user->id)->whereNull('locked_at')->first()
            ?->update(['locked_at' => now(), 'locked_on_register_id' => $registerId]);
    }

    /**
     * Ends $user's lockout. Returns whether they were locked.
     */
    public function unlock(User $user): bool
    {
        $tillPin = TillPin::query()->where('user_id', $user->id)->whereNotNull('locked_at')->first();

        return $tillPin !== null && $tillPin->update(['locked_at' => null, 'locked_on_register_id' => null]);
    }

    public function isLocked(User $user): bool
    {
        return TillPin::query()->where('user_id', $user->id)->whereNotNull('locked_at')->exists();
    }

    public function has(User $user): bool
    {
        return TillPin::query()->where('user_id', $user->id)->exists();
    }
}
