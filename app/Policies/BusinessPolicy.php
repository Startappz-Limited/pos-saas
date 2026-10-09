<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * Exporting and closing a business are for its owner alone: not co-admins,
 * and not a super-admin (Gate::before defers to this policy for a Business).
 */
class BusinessPolicy
{
    public function export(User $user, Business $business): bool
    {
        return $this->owns($user, $business) && ! $business->isClosing();
    }

    public function close(User $user, Business $business): bool
    {
        return $this->owns($user, $business) && ! $business->isClosing();
    }

    public function cancelClosure(User $user, Business $business): bool
    {
        return $this->owns($user, $business) && $business->isClosing();
    }

    private function owns(User $user, Business $business): bool
    {
        return $business->owner_id !== null && $business->owner_id === $user->id;
    }
}
