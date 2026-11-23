<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\Business;
use App\Models\CostLayer;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Records when a business's stock ledger began (stage 11c, step 4): the time of its first batch. A day before it can't
 * be rebuilt, so its snapshot takes the current figures, marked late. A dry run shows the time; `--apply` records it.
 * A ledger start already recorded is kept (it is never moved later).
 */
class StockLedgerStartCommand extends Command
{
    use RunsForBusiness;

    protected $signature = 'stock:ledger-start {--apply : Record the start (without it, a dry run)}';

    protected $description = 'Record when the business\'s stock ledger began (its first batch), so earlier days are marked late, not rebuilt';

    public function handleForBusiness(TenantContext $tenant): int
    {
        $business = $tenant->business();
        if (! $business instanceof Business) {
            $this->error('No business is set.');

            return self::FAILURE;
        }

        if ($business->getRawOriginal('ledger_started_at') !== null) {
            $this->info('The ledger began '.$business->ledger_started_at->toDateTimeString().' UTC; nothing to change.');

            return self::SUCCESS;
        }

        $first = CostLayer::query()->min('created_at');
        if ($first === null) {
            $this->warn('The business has no batch yet: its ledger starts with its first one.');

            return self::SUCCESS;
        }

        $start = CarbonImmutable::parse($first, 'UTC');
        $this->info('The first batch was recorded at '.$start->toDateTimeString().' UTC.');

        if (! $this->option('apply')) {
            $this->line('Dry run: nothing was changed. Run with --apply to record it.');

            return self::SUCCESS;
        }

        // Quietly: a bookkeeping value, not an audited change.
        $business->forceFill(['ledger_started_at' => $start])->saveQuietly();
        $this->info('Recorded as the ledger start.');

        return self::SUCCESS;
    }
}
