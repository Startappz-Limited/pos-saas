<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    /**
     * Create a new user
     */
    public function execute(array $data): User
    {
        $shopIds = $data['shop_ids'] ?? [];
        unset($data['role_id'], $data['roles'], $data['shop_ids']);

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Set default status if not provided
        if (! isset($data['status'])) {
            $data['status'] = UserStatus::ACTIVE;
        }

        $user = User::create($data);
        $user->shops()->sync($shopIds);

        return $user;
    }
}
