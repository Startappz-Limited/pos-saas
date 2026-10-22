<?php

namespace App\Policies;

use App\Models\Attribute;
use App\Models\User;

/**
 * Product attributes (size, colour, ...), Phase 5: one permission per action.
 * The controller had no authorization at all. Another business's attribute
 * never reaches the policy (its scope makes it a 404).
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
     * Editing, and switching an attribute on or off.
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
