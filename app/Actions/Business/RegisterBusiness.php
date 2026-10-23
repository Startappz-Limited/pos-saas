<?php

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Self-service signup (decision 4.4; Phase 6): in one transaction, a new
 * login, its business, the owner membership (role `admin`), the role template
 * copies, the first shop and the trial subscription, counted from now.
 * The business is usable at once; the owner has 24 hours to verify their
 * email (`verification_deadline_at`), after which access is blocked until
 * they do.
 */
class RegisterBusiness
{
    public const VERIFICATION_GRACE_HOURS = 24;

    public function __construct(private TenantContext $tenant, private StartTrial $trial) {}

    /**
     * @param  array{name: string, email: string, password: string, business_name: string, plan?: ?string, shop_count?: int|string|null}  $data
     */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $plan = $this->plan($data['plan'] ?? null);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['verification_deadline_at' => now()->addHours(self::VERIFICATION_GRACE_HOURS)])->saveQuietly();

            $business = Business::create([
                'name' => $data['business_name'],
                'status' => Business::STATUS_ACTIVE,
                'trial_started_at' => now(),
                'primary_owner_id' => $user->id,
            ]);

            $user->joinBusiness($business);

            $this->tenant->run($business, function () use ($user, $business, $plan, $data): void {
                $user->assignRole(CopyRoleTemplates::OWNER_ROLE);

                // The subscription first, so the first shop is counted against it.
                $this->trial->execute($business, $plan, (int) ($data['shop_count'] ?? 1));

                Shop::create(['business_id' => $business->id, 'name' => $data['business_name']]);
            });

            return $user;
        });
    }

    /**
     * The plan picked at signup: a tier's key, or the Free Trial (the default
     * when nothing valid is picked).
     */
    private function plan(?string $key): Plan
    {
        return Plan::query()->where('is_active', true)
            ->when($key !== null, fn ($q) => $q->where('key', $key))
            ->when($key === null, fn ($q) => $q->where('is_trial_plan', true))
            ->first() ?? Plan::query()->where('is_trial_plan', true)->firstOrFail();
    }
}
