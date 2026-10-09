<?php

namespace App\Policies;

use App\Models\PricingRule;
use App\Models\User;

class PricingRulePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('pricing.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PricingRule $pricingRule): bool
    {
        return $user->can('pricing.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('pricing.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PricingRule $pricingRule): bool
    {
        return $user->can('pricing.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PricingRule $pricingRule): bool
    {
        return $user->can('pricing.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PricingRule $pricingRule): bool
    {
        return $user->can('pricing.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PricingRule $pricingRule): bool
    {
        return $user->can('pricing.delete');
    }
}
