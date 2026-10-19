<?php

namespace App\Rules;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A role the signed-in user may give someone in the current business (stage
 * 3): one of the business's roles, and no more than the giver holds. Only an
 * owner gives the owner role; anyone else only a role whose permissions they
 * all hold. Used by invites and by adding or editing users (web and API).
 */
class GrantableRole implements ValidationRule
{
    /**
     * @param  bool  $byName  the value is a role name rather than an id
     */
    public function __construct(private ?User $giver, private bool $byName = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $role = $this->byName
            ? Role::query()->where('name', (string) $value)->where('guard_name', 'web')->first()
            : (is_numeric($value) ? Role::query()->find((int) $value) : null);

        if ($role === null) {
            $fail(__('Choose one of this business\'s roles.'));

            return;
        }

        if ($this->giver === null || $this->giver->isBusinessOwner()) {
            return;
        }

        $held = $this->giver->getAllPermissions()->pluck('name');

        if ($role->name === CopyRoleTemplates::OWNER_ROLE || $role->permissions->pluck('name')->diff($held)->isNotEmpty()) {
            $fail(__('You can only give a role whose permissions you hold.'));
        }
    }
}
