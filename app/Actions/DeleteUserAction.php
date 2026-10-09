<?php

namespace App\Actions;

use App\Models\User;

class DeleteUserAction
{
    /**
     * Delete a user (soft delete)
     */
    public function execute(User $user): bool
    {
        return $user->delete();
    }
}
