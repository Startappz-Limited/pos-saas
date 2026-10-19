<?php

namespace App\Actions\Business;

use App\Models\BusinessMembership;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Removes a person from the current business (stage 3): their membership is
 * made inactive, their roles and shop assignments here go, and their
 * `/api/v1` tokens for it are revoked, so they lose access at once. The login
 * itself stays: it may belong to other businesses, and its records here keep
 * their author. Inviting them again restores the membership.
 */
class RemoveFromBusiness
{
    public function __construct(private TenantContext $tenant) {}

    public function execute(User $user): void
    {
        $businessId = $this->tenant->id();

        DB::transaction(function () use ($user, $businessId): void {
            BusinessMembership::query()
                ->where('business_id', $businessId)
                ->where('user_id', $user->id)
                ->update(['status' => BusinessMembership::STATUS_INACTIVE]);

            // Roles are held per business: this detaches only this business's.
            $user->syncRoles([]);

            // Run by an owner, who sees every shop of the business.
            $user->shops()->detach(Shop::query()->pluck('id'));

            PersonalAccessToken::query()
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->id)
                ->where('business_id', $businessId)
                ->delete();
        });
    }
}
