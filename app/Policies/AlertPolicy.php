<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class AlertPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'alerts', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Alert $alert): bool
    {
        return $user->can('alerts.view') && $this->sharesShop($user, $alert);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('alerts.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Alert $alert): bool
    {
        return $user->can('alerts.update') && $this->sharesShop($user, $alert);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Alert $alert): bool
    {
        return $user->can('alerts.delete') && $this->sharesShop($user, $alert);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Alert $alert): bool
    {
        return $this->delete($user, $alert);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Alert $alert): bool
    {
        return $this->delete($user, $alert);
    }

    /**
     * Alerts are shop-owned, but a null shop_id means the alert is system-wide and
     * visible to every user. Anything else requires access to the owning shop so a
     * user cannot read or action another shop's alerts.
     */
    private function sharesShop(User $user, Alert $alert): bool
    {
        if ($alert->shop_id === null) {
            return true;
        }

        return $user->canAccessShop($alert->shop_id);
    }
}
