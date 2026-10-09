<?php

namespace App\Policies;

use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('campaigns.view');
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.view') && $this->sharesShop($user, $campaign);
    }

    public function create(User $user): bool
    {
        return $user->can('campaigns.create');
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.update') && $this->sharesShop($user, $campaign);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete') && $this->sharesShop($user, $campaign);
    }

    public function publish(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.publish') && $this->sharesShop($user, $campaign);
    }

    public function restore(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete') && $this->sharesShop($user, $campaign);
    }

    public function forceDelete(User $user, Campaign $campaign): bool
    {
        return $user->can('campaigns.delete') && $this->sharesShop($user, $campaign);
    }

    private function sharesShop(User $user, Campaign $campaign): bool
    {
        if ($campaign->shop_id === null) {
            return true;
        }

        return $user->canAccessShop($campaign->shop_id);
    }
}
