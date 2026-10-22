<?php

namespace App\Actions\Role;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Sets a role's permissions without letting the editor give more than they
 * hold (Phase 5: no escalation through role editing). An owner gives any
 * permission; anyone else only the permissions they hold themselves, and the
 * role's permissions outside their reach are kept as they are, so editing a
 * role they can only partly see never strips the rest.
 */
class SyncRolePermissions
{
    /**
     * The ids of the permissions $editor may give, or null for every one.
     *
     * @return Collection<int, int>|null
     */
    public function givableIds(User $editor): ?Collection
    {
        if ($editor->isBusinessOwner()) {
            return null;
        }

        return $editor->getAllPermissions()->pluck('id')->map(fn ($id): int => (int) $id)->values();
    }

    /**
     * The ids among $ids that $editor may not give.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int>
     */
    public function refusedIds(User $editor, array $ids): array
    {
        $givable = $this->givableIds($editor);

        if ($givable === null) {
            return [];
        }

        return collect($ids)->map(fn ($id): int => (int) $id)->diff($givable)->values()->all();
    }

    /**
     * @param  array<int, int|string>  $ids  the permissions chosen on the form
     */
    public function execute(Role $role, User $editor, array $ids): void
    {
        $chosen = collect($ids)->map(fn ($id): int => (int) $id)->unique();
        $givable = $this->givableIds($editor);

        if ($givable !== null) {
            $outOfReach = $role->permissions()->pluck('permissions.id')->map(fn ($id): int => (int) $id)->diff($givable);
            $chosen = $chosen->intersect($givable)->merge($outOfReach)->unique();
        }

        $role->syncPermissions($chosen->values()->all());
    }
}
