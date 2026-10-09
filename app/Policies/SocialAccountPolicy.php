<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;

class SocialAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social-accounts.view');
    }

    public function view(User $user, SocialAccount $account): bool
    {
        return $user->can('social-accounts.view') && $this->sharesShop($user, $account);
    }

    public function create(User $user): bool
    {
        return $user->can('social-accounts.connect');
    }

    public function update(User $user, SocialAccount $account): bool
    {
        return $user->can('social-accounts.connect') && $this->sharesShop($user, $account);
    }

    public function delete(User $user, SocialAccount $account): bool
    {
        return $user->can('social-accounts.disconnect') && $this->sharesShop($user, $account);
    }

    private function sharesShop(User $user, SocialAccount $account): bool
    {
        return $user->shops()->where('shops.id', $account->shop_id)->exists();
    }
}
