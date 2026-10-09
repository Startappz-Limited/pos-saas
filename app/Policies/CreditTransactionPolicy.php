<?php

namespace App\Policies;

use App\Models\CreditTransaction;
use App\Models\User;

class CreditTransactionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('credit-sales.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('credit-sales.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CreditTransaction $creditTransaction): bool
    {
        return $user->can('credit-sales.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('credit-sales.create') || $user->can('credit-sales.collect');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CreditTransaction $creditTransaction): bool
    {
        return $user->can('credit-sales.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CreditTransaction $creditTransaction): bool
    {
        return $user->can('credit-sales.write-off');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CreditTransaction $creditTransaction): bool
    {
        return $user->can('credit-sales.write-off');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CreditTransaction $creditTransaction): bool
    {
        return false;
    }
}
