<?php

namespace App\Support\Authorization;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Which roles hold a permission because someone in the business gave it to
 * them, as opposed to having it from their template (stage 10, step 2).
 *
 * The audit trail records every Spatie permission attach on a role with who
 * did it (`permissions_attached`, since the first commit). Template copying and
 * the seeder write rows directly, so a role's permission that came from its
 * template has no attach by a person. A role counts as deliberately granted
 * when the latest audited attach or detach of the permission on it is an
 * attach by a user who isn't a platform operator. With no readable audit
 * trail, none count (and a warning is logged).
 */
final class DeliberateGrants
{
    /**
     * @param  iterable<int>  $roleIds
     * @return list<int> the roles among $roleIds an owner gave $permission to
     */
    public function roles(string $permission, iterable $roleIds): array
    {
        $roleIds = collect($roleIds)->map(fn ($id): int => (int) $id)->all();
        if ($roleIds === []) {
            return [];
        }

        try {
            $rows = AuditLog::query()
                ->where('auditable_type', Role::class)
                ->whereIn('auditable_id', $roleIds)
                ->where('new_values', 'like', '%'.$permission.'%')
                ->orderBy('id')
                ->get(['auditable_id', 'user_id', 'new_values']);
        } catch (\Throwable $e) {
            Log::warning('Deliberate grants unknown: the audit trail could not be read.', ['permission' => $permission, 'error' => $e->getMessage()]);

            return [];
        }

        $latest = [];
        foreach ($rows as $row) {
            $values = (array) $row->new_values;
            if (in_array($permission, (array) ($values['permissions_attached'] ?? []), true)) {
                $latest[(int) $row->auditable_id] = $row->user_id === null ? null : (int) $row->user_id;
            } elseif (in_array($permission, (array) ($values['permissions_detached'] ?? []), true)) {
                $latest[(int) $row->auditable_id] = null;
            }
        }

        $people = array_values(array_filter($latest));
        $operators = User::query()->whereIn('id', $people)->where('is_platform_operator', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return array_values(array_keys(array_filter($latest, fn (?int $userId): bool => $userId !== null && ! in_array($userId, $operators, true))));
    }
}
