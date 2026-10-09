<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\FullAccess;

/**
 * `{resource}.full-access` grants every permission on the resource, but not a
 * way around the policy's rules. It used to make before() return true, which
 * also skipped the immutable-record, workflow-state and shop checks; since an
 * admin holds every permission, that made admins as unrestricted as a
 * super-admin. Now the ability is still decided by the policy method, with the
 * resource's permission checks treated as passed. Only a super-admin
 * (Gate::before) bypasses the rules.
 */
trait HandlesFullAccess
{
    /**
     * @param  array<int, mixed>  $arguments  as passed to before()
     */
    protected function decideWithFullAccess(User $user, string $resource, string $ability, array $arguments): ?bool
    {
        if (! $user->can("{$resource}.full-access")) {
            return null;
        }

        // An ability this policy has no rules for: full access grants it
        if (! method_exists($this, $ability)) {
            return true;
        }

        // Gate passes the class name for abilities such as viewAny/create;
        // the policy methods do not take it
        if (isset($arguments[0]) && is_string($arguments[0])) {
            array_shift($arguments);
        }

        return FullAccess::grant($user, $resource, fn (): bool => (bool) $this->{$ability}($user, ...$arguments));
    }
}
