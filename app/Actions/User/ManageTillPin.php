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
        $tillPin->fill(['pin_hash' => Hash::make($pin), 'set_at' => now()])->save();

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

    public function has(User $user): bool
    {
        return TillPin::query()->where('user_id', $user->id)->exists();
    }
}
