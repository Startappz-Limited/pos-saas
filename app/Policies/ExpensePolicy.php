<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

class ExpensePolicy
{
    use HandlesFullAccess;

    /**
     * Full access grants every permission on the resource; the rules in the
     * ability methods still apply (see HandlesFullAccess).
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'expenses', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Expense $expense): bool
    {
        if ($user->can('expenses.view-all') && $user->canAccessShop($expense->shop_id)) {
            return true;
        }

        return $user->can('expenses.view') && $user->canAccessShop($expense->shop_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Expense $expense): bool
    {
        if ($user->can('expenses.edit-all') && $user->canAccessShop($expense->shop_id)) {
            return true;
        }

        return $user->can('expenses.update') && $user->canAccessShop($expense->shop_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        if ($user->can('expenses.delete-all') && $user->canAccessShop($expense->shop_id)) {
            return true;
        }

        return $user->can('expenses.delete') && $user->canAccessShop($expense->shop_id);
    }

    /**
     * Determine whether the user can approve the expense.
     */
    public function approve(User $user, Expense $expense): bool
    {
        return $user->can('expenses.approve') && $user->canAccessShop($expense->shop_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $user->can('expenses.delete') && $user->canAccessShop($expense->shop_id);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return $user->can('expenses.delete') && $user->canAccessShop($expense->shop_id);
    }
}
