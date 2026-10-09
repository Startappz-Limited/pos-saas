<?php

namespace App\Policies;

use App\Models\CreditAccount;
use App\Models\User;

class CreditAccountPolicy
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
    public function view(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('credit-sales.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.write-off') && (float) $creditAccount->current_balance <= 0;
    }

    public function updateLimit(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.update');
    }

    public function suspend(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.update');
    }

    public function reactivate(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.update');
    }

    /**
     * Send the customer their outstanding-debt statement over WhatsApp.
     *
     * Gated on `collect` rather than `view`: this messages a customer about
     * money, which is chasing payment, not reading a record.
     */
    public function sendStatement(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.collect');
    }

    public function viewOverdue(User $user): bool
    {
        return $user->can('credit-sales.view');
    }

    public function viewAgingReport(User $user): bool
    {
        return $user->can('credit-sales.view');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CreditAccount $creditAccount): bool
    {
        return $user->can('credit-sales.write-off');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CreditAccount $creditAccount): bool
    {
        return false;
    }
}
