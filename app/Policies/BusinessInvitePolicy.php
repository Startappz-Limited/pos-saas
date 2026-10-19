<?php

namespace App\Policies;

use App\Models\BusinessInvite;
use App\Models\User;

/**
 * Inviting is adding a user to the business, so it takes `users.create`.
 * Which role an invite may carry is checked by StoreBusinessInviteRequest.
 */
class BusinessInvitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.create');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function resend(User $user, BusinessInvite $invite): bool
    {
        return $user->can('users.create') && $invite->isPending();
    }

    public function delete(User $user, BusinessInvite $invite): bool
    {
        return $user->can('users.create') && $invite->isPending();
    }
}
