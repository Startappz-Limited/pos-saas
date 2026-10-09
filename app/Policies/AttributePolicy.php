<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

/**
 * Product attributes (size, colour...). Attributes belong to a business, so
 * another business's are already hidden by BelongsToBusiness (404); this
 * policy decides what a user may do with their own business's.
 */
class AttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attributes.view');
    }

    public function view(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attributes.create');
    }

    /**
     * Also covers activating and deactivating.
     */
    public function update(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.update');
    }

    public function delete(User $user, Attribute $attribute): bool
    {
        return $user->can('attributes.delete');
    }
}
