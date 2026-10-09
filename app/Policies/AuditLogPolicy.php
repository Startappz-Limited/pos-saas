<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;

/**
 * Audit records are read-only. There is intentionally no create/update/delete
 * ability — nothing in the application may mutate the trail.
 */
class AuditLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('audit.full-access')) {
            return true;
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

        // Rows with no shop context are system-wide and visible to any auditor.
        if ($auditLog->shop_id === null) {
            return true;
        }

        return $user->canAccessShop((int) $auditLog->shop_id);
    }

    public function export(User $user): bool
    {
        return $user->can('audit.export');
    }
}
