<?php

namespace App\Actions;

use App\Models\User;

class DeleteUserAction
{
    /**
     * Delete a user permanently (User has no soft deletes); UserService::delete
     * erases their personal data around it
     */
    public function execute(User $user): bool
    {
        return $user->delete();
    }
}
