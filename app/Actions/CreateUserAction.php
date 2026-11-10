<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;

class CreateUserAction
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Create a new user
     */
    public function execute(array $data): User
    {
        $business = $this->tenant->business();
        // On the Free Trial, every member gets the default shop, whatever
        // was asked for (stage 10a, step 3).
        $shopIds = $business?->onFreeTrial()
            ? array_filter([$business->default_shop_id])
            : ($data['shop_ids'] ?? []);
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
