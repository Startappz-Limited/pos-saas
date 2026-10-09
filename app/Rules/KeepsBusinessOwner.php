<?php

namespace App\Rules;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Closure;

/**
 * Validation that keeps a business owner in charge of their business: only a
 * super-admin may take the admin role away from an owner or make them anything
 * but active. (UserPolicy already stops other users from editing an owner;
 * this covers the owner editing their own account.)
 */
class KeepsBusinessOwner
{
    public static function roles(?User $target, ?User $actor): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($target, $actor): void {
            if (! self::applies($target, $actor) || ! is_array($value)) {
                return;
            }

            if (! in_array(Role::ADMIN, $value, true)) {
                $fail(__('The business owner must keep the admin role.'));
            }
        };
    }

    public static function status(?User $target, ?User $actor): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($target, $actor): void {
            if (! self::applies($target, $actor) || $value === null) {
                return;
            }

            $status = $value instanceof UserStatus ? $value : UserStatus::tryFrom((string) $value);

            if ($status !== UserStatus::ACTIVE) {
                $fail(__('The business owner must stay active.'));
            }
        };
    }

    private static function applies(?User $target, ?User $actor): bool
    {
        return $target !== null
            && ! ($actor?->isSuperAdmin() ?? false)
            && $target->isBusinessOwner();
    }
}
