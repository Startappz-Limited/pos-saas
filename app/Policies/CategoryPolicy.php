<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->can('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        if (! $user->can('categories.delete')) {
            return false;
        }

        // Prevent deletion if category has products
        if ($category->products()->exists()) {
            return false;
        }

        return true;
    }

    public function restore(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }

    public function forceDelete(User $user, Category $category): bool
    {
        return $user->can('categories.delete');
    }
}
