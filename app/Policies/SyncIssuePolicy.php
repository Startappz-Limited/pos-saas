<?php

namespace App\Policies;

use App\Models\SyncIssue;
use App\Models\User;

/**
 * The till sync review screen (Phase 7): owners and managers by default.
 */
class SyncIssuePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sync-issues.view');
    }

    public function resolve(User $user, SyncIssue $issue): bool
    {
        return $user->can('sync-issues.resolve') && $issue->resolved_at === null;
    }
}
