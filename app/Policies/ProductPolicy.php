<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }

    /**
     * Seeing the purchase cost is gated separately from seeing the product.
     * Cost reveals supplier terms and margin, which most floor staff have no
     * business reading, so `products.view` alone is not enough. Anyone trusted
     * to set the cost can obviously read it, hence the implication.
     */
    public function viewCost(User $user): bool
    {
        return $user->can('products.view-cost') || $user->can('products.set-cost');
    }

    /**
     * Setting the purchase cost is gated separately from a general product
     * edit: it drives COGS and profit reporting, so it is restricted to the
     * roles accountable for margin rather than anyone who can rename a product.
     */
    public function setCost(User $user): bool
    {
        return $user->can('products.set-cost');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }
}
