<?php

namespace App\Console\Commands;

use App\Actions\Stock\PruneDailySnapshots;
use Illuminate\Console\Command;

/**
 * Deletes daily stock snapshots older than 13 months; month-end and manual snapshots are kept (stage 11c, step 3).
 */
class StockPruneSnapshotsCommand extends Command
{
    protected $signature = 'stock:prune-snapshots';

    protected $description = 'Delete daily stock snapshots older than 13 months (month-end and manual ones are kept)';

    public function handle(PruneDailySnapshots $prune): int
    {
        $this->info('Deleted '.$prune->execute().' daily snapshot(s).');

        return self::SUCCESS;
    }
}
