<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Support\Authorization\RolloutBackfill;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The role rollout backfill for one business (stage 10, step 0), for reports
 * and re-runs: the migration that created `role_revoked_permissions` already
 * ran it for every business. A dry run unless `--apply`; idempotent.
 */
class BackfillRoleRollout extends Command
{
    use RunsForBusiness;

    protected $signature = 'roles:backfill-rollout
        {--apply : Grant the missed permissions and record the other absences (a dry run otherwise)}';

    protected $description = 'Grant the permissions the old rollout missed and record the other absences as revoked';

    public function handleForBusiness(RolloutBackfill $backfill, TenantContext $tenant): int
    {
        $apply = (bool) $this->option('apply');
        $report = DB::transaction(fn (): array => $backfill->run((int) $tenant->id(), $apply));

        if ($report === []) {
            $this->info('Every role copy already holds its template\'s permissions, apart from recorded removals.');

            return self::SUCCESS;
        }

        $this->table(['Role', 'Granted (missed by the old rollout)', 'Recorded as revoked'], array_map(fn (array $row): array => [
            $row['role'], implode(', ', $row['granted']) ?: '-', implode(', ', $row['revoked']) ?: '-',
        ], $report));

        $this->info(($apply ? 'Applied.' : 'Dry run: nothing was changed. Run again with --apply.'));

        return self::SUCCESS;
    }
}
