<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.view');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.delete');
    }

    /**
     * Determine whether the user can activate the customer.
     */
    public function activate(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.activate');
    }

    /**
     * Determine whether the user can deactivate the customer.
     */
    public function deactivate(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.deactivate');
    }

    /**
     * Determine whether the user can manage customer credit.
     */
    public function manageCredit(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.manage-credit');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        return $user->hasPermissionTo('customers.delete');
    }
}
