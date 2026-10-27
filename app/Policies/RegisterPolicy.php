<?php

namespace App\Policies;

use App\Models\Register;
use App\Models\User;

/**
 * Registers (tills), Phase 7. The route binding already limits registers to
 * the viewer's shops, so another shop's register is a 404 before this runs.
 */
class RegisterPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('registers.view');
    }

    public function pair(User $user, Register $register): bool
    {
        return $user->can('registers.pair') && $user->canAccessShop($register->shop_id);
    }
}
