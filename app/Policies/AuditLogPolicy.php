<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use App\Policies\Concerns\HandlesFullAccess;

/**
 * Audit records are read-only. There is intentionally no create/update/delete
 * ability — nothing in the application may mutate the trail.
 */
class AuditLogPolicy
{
    use HandlesFullAccess;

    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        // Full access grants the permissions, not a way around the rules below
        if (($decision = $this->decideWithFullAccess($user, 'audit', $ability, $arguments)) !== null) {
            return $decision;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $user->can('audit.view')) {
            return false;
        }

        // Rows with no shop context belong to the business of whoever acted
        if ($auditLog->shop_id === null) {
            return $auditLog->user_id !== null
                && User::query()->visibleTo($user)->whereKey($auditLog->user_id)->exists();
        }

        return $user->canAccessShop((int) $auditLog->shop_id);
    }

    public function export(User $user): bool
    {
        return $user->can('audit.export');
    }
}
