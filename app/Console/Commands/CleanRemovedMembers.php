<?php

namespace App\Console\Commands;

use App\Actions\Business\RemoveFromBusiness;
use App\Console\Concerns\RunsForBusiness;
use App\Models\BusinessMembership;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Brings every removed membership of a business to the state a fresh
 * removal leaves (stage 10a, step 1): the till PIN gone, pending invites
 * from before the removal revoked, the default business moved on, and
 * unbound tokens deleted once nothing usable is left. Dry run by default.
 *
 * A membership removed before this stage has no `removed_at` to key off: for
 * those, and only once, a membership that is inactive and holds no role is
 * treated as removed, with `removed_at` backfilled from `updated_at` (the
 * best date there is) on `--apply`. Running this safely does nothing more on
 * a second `--apply`: every step it takes is a no-op once there is nothing
 * left for it to do.
 */
class CleanRemovedMembers extends Command
{
    use RunsForBusiness;

    protected $signature = 'members:clean-removed
        {--apply : Apply the changes (without it, only report)}';

    protected $description = 'Settle every removed membership of a business: till PIN, pending invites, default business and unbound tokens (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    protected function allBusinessesEveryStatus(): bool
    {
        return true;
    }

    public function handleForBusiness(RemoveFromBusiness $remover, TenantContext $tenant): int
    {
        $apply = (bool) $this->option('apply');
        $businessId = (int) $tenant->id();

        $toBackfill = BusinessMembership::query()
            ->where('business_id', $businessId)
            ->where('status', BusinessMembership::STATUS_INACTIVE)
            ->whereNull('removed_at')
            ->with('user')
            ->get()
            ->filter(fn (BusinessMembership $membership): bool => $membership->user !== null && $membership->user->roles()->count() === 0);

        if ($toBackfill->isNotEmpty()) {
            $this->warn('Removed before this stage (no role) — removed_at backfilled from their last update:');
            $this->table(['User', 'Email', 'removed_at (from updated_at)'], $toBackfill->map(fn (BusinessMembership $membership): array => [
                $membership->user->name, $membership->user->email, $membership->updated_at->toDateTimeString(),
            ])->all());

            if ($apply) {
                foreach ($toBackfill as $membership) {
                    $membership->forceFill(['removed_at' => $membership->updated_at])->save();
                }
            }
        }

        $removed = BusinessMembership::query()
            ->where('business_id', $businessId)
            ->whereNotNull('removed_at')
            ->with('user')
            ->get()
            ->filter(fn (BusinessMembership $membership): bool => $membership->user !== null);

        if ($removed->isEmpty()) {
            $this->info($toBackfill->isEmpty() ? 'Nothing to clean.' : 'Dry run: nothing changed. Run again with --apply.');

            return self::SUCCESS;
        }

        $this->table(['User', 'Email', 'Removed at'], $removed->map(fn (BusinessMembership $membership): array => [
            $membership->user->name, $membership->user->email, $membership->removed_at->toDateTimeString(),
        ])->all());

        if (! $apply) {
            $this->warn('Dry run: nothing changed. Run again with --apply.');

            return self::SUCCESS;
        }

        foreach ($removed as $membership) {
            $remover->settle($membership->user, $businessId, $membership->removed_at, 'members:clean-removed');
        }

        $this->info('Settled.');

        return self::SUCCESS;
    }
}
