<?php

namespace App\Console\Commands;

use App\Actions\Stock\TakeNightlySnapshots;
use Illuminate\Console\Command;

/**
 * Takes each shop's nightly stock snapshot once its local midnight has passed (stage 11c, step 3). Scheduled every
 * 15 minutes, so a shop is snapshotted within a quarter hour of its own midnight.
 */
class StockSnapshotCommand extends Command
{
    protected $signature = 'stock:snapshot';

    protected $description = 'Take each shop\'s stock snapshot for the day that ended, once its local midnight has passed';

    public function handle(TakeNightlySnapshots $nightly): int
    {
        $this->info('Took '.$nightly->execute().' snapshot row(s).');

        return self::SUCCESS;
    }
}
