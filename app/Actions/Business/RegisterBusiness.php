<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service signup (decision 4.4, stage 3): a new login, its business and
 * its owner membership. The business waits for the owner's email to be
 * verified before anyone can work in it, and its trial starts then
 * (StartTrialOnEmailVerified).
 */
class RegisterBusiness
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array{name: string, email: string, password: string, business_name: string}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $business = Business::create([
                'name' => $data['business_name'],
                'status' => Business::STATUS_PENDING_VERIFICATION,
            ]);

            $user->joinBusiness($business);
            $this->tenant->run($business, fn () => $user->assignRole(CopyRoleTemplates::OWNER_ROLE));

            return $user;
        });
    }
}
