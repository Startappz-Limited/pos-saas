<?php

namespace App\Policies;

use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Super-admin has unrestricted access
        if ($user->hasRole('super-admin')) {
            return true;
        }

        // Full-access permission grants all abilities
        if ($user->can('expense-categories.full-access')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('expense-categories.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expense-categories.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('expense-categories.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expense-categories.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expense-categories.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expense-categories.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $user->can('expense-categories.delete');
    }
}
