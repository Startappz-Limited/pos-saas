<?php

namespace App\Support\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The one-time backfill of `role_revoked_permissions` (stage 10, step 0). Before
 * this stage nothing recorded a permission taken off a role, so for each
 * business's copy of a template, and each permission the template holds that
 * the copy lacks:
 * - a permission from Phase 5 or 7 that the old timestamp-based rollout missed
 *   (`attributes.*`, `stock.view`, `registers.*`, `sync-issues.view` and
 *   `.resolve`) is granted;
 * - any other absence is recorded as revoked, since it can't be told apart from
 *   a deliberate removal;
 * - this stage's new permissions are skipped, so the first `permissions:sync`
 *   gives them to the copies.
 *
 * Run inside the migration that creates the table (every business, before any
 * new-style `RoleSeeder` can run) and by `roles:backfill-rollout` (one business,
 * a dry run unless applied). Idempotent: a second run finds nothing.
 */
final class RolloutBackfill
{
    /** Permissions the old rollout missed: granted. */
    public const MISSED = ['attributes.*', 'stock.view', 'registers.*', 'sync-issues.view', 'sync-issues.resolve'];

    /** This stage's new permissions: neither granted nor recorded here. */
    public const NEW = ['cash-registers.payout', 'sync-issues.accept', 'sync-issues.discard'];

    /**
     * @return list<array{business_id: int, role: string, granted: list<string>, revoked: list<string>}>
     */
    public function run(?int $businessId, bool $apply): array
    {
        $copies = DB::table('roles')->whereNotNull('template_id')->whereNotNull('business_id')
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->orderBy('business_id')->orderBy('name')->get(['id', 'name', 'business_id', 'template_id']);
        $report = [];

        foreach ($copies as $copy) {
            $missing = DB::table('role_has_permissions as t')
                ->join('permissions', 'permissions.id', '=', 't.permission_id')
                ->where('t.role_id', $copy->template_id)
                ->whereNotExists(fn ($query) => $query->from('role_has_permissions as c')
                    ->whereColumn('c.permission_id', 't.permission_id')->where('c.role_id', $copy->id))
                ->whereNotExists(fn ($query) => $query->from('role_revoked_permissions as r')
                    ->whereColumn('r.permission_id', 't.permission_id')->where('r.role_id', $copy->id))
                ->pluck('permissions.name', 'permissions.id');

            $missing = $missing->reject(fn (string $name): bool => in_array($name, self::NEW, true));
            $granted = $missing->filter(fn (string $name): bool => Str::is(self::MISSED, $name));
            $revoked = $missing->diffKeys($granted);

            if ($granted->isEmpty() && $revoked->isEmpty()) {
                continue;
            }

            if ($apply) {
                DB::table('role_has_permissions')->insertOrIgnore(
                    $granted->keys()->map(fn ($id): array => ['permission_id' => $id, 'role_id' => $copy->id])->all()
                );
                RoleRevocations::change((int) $copy->id, $revoked->keys());
            }

            $report[] = [
                'business_id' => (int) $copy->business_id,
                'role' => $copy->name,
                'granted' => $granted->values()->sort()->values()->all(),
                'revoked' => $revoked->values()->sort()->values()->all(),
            ];
        }

        return $report;
    }
}
