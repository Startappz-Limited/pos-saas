<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUserAction
{
    /**
     * Update an existing user
     */
    public function execute(User $user, array $data): User
    {
        $shopIds = $data['shop_ids'] ?? [];
        unset($data['role_id'], $data['roles'], $data['shop_ids']);

        // Hash password if provided and changed
        if (isset($data['password']) && ! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Remove password from update if not provided
            unset($data['password']);
        }

        $user->update($data);
        $user->shops()->sync($shopIds);

        return $user->fresh(['shops']);
    }
}
