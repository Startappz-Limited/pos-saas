<?php

namespace App\Support\Authorization;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Which permissions were taken off a role on purpose (stage 10, step 0), in
 * `role_revoked_permissions`. The rollout (`RoleSeeder`) gives a template its
 * listed permissions, and each business's copy its template's, minus these, so
 * a permission a business or the platform console took away stays away however
 * often the seeder runs, while one nobody took away always arrives.
 *
 * The role screens write a row when they take a permission off a role and
 * delete it when they give it back; "Reset to default" and "Apply to all copies"
 * clear a copy's rows; a migration that revokes a permission writes them too.
 */
final class RoleRevocations
{
    /**
     * @return Collection<int, int> the permission ids revoked from $roleId
     */
    public static function ids(int $roleId): Collection
    {
        return DB::table('role_revoked_permissions')->where('role_id', $roleId)->pluck('permission_id')->map(fn ($id): int => (int) $id);
    }

    /**
     * Records that $removed were taken off $roleId and forgets those in $added,
     * which were given back.
     *
     * @param  iterable<int, int|string>  $removed
     * @param  iterable<int, int|string>  $added
     */
    public static function change(int $roleId, iterable $removed, iterable $added = [], ?int $by = null): void
    {
        $now = now();
        $removed = collect($removed)->map(fn ($id): int => (int) $id)->unique()->values();
        $added = collect($added)->map(fn ($id): int => (int) $id)->unique()->values();

        if ($removed->isNotEmpty()) {
            DB::table('role_revoked_permissions')->insertOrIgnore($removed->map(fn (int $id): array => [
                'role_id' => $roleId, 'permission_id' => $id, 'revoked_by' => $by, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        }

        if ($added->isNotEmpty()) {
            DB::table('role_revoked_permissions')->where('role_id', $roleId)->whereIn('permission_id', $added->all())->delete();
        }
    }

    /**
     * Forgets every revocation of $roleId: it now holds exactly its template's permissions.
     */
    public static function clear(int $roleId): void
    {
        DB::table('role_revoked_permissions')->where('role_id', $roleId)->delete();
    }

    /**
     * Records the difference between what a role held before an edit and after it.
     *
     * @param  iterable<int, int|string>  $before  permission ids
     * @param  iterable<int, int|string>  $after  permission ids
     */
    public static function recordEdit(int $roleId, iterable $before, iterable $after, ?int $by = null): void
    {
        $before = collect($before)->map(fn ($id): int => (int) $id);
        $after = collect($after)->map(fn ($id): int => (int) $id);

        self::change($roleId, $before->diff($after), $after->diff($before), $by);
    }
}
