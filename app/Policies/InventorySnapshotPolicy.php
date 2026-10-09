<?php

namespace App\Policies;

use App\Models\InventorySnapshot;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class InventorySnapshotPolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'inventory-snapshots', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('inventory-snapshots.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InventorySnapshot $inventorySnapshot): bool
    {
        return $user->can('inventory-snapshots.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('inventory-snapshots.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InventorySnapshot $inventorySnapshot): bool
    {
        return false; // Snapshots are immutable once created
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InventorySnapshot $inventorySnapshot): bool
    {
        return $user->can('inventory-snapshots.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, InventorySnapshot $inventorySnapshot): bool
    {
        return $user->can('inventory-snapshots.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, InventorySnapshot $inventorySnapshot): bool
    {
        return $user->can('inventory-snapshots.delete');
    }
}
