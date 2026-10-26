<?php

namespace App\Console\Commands;

use App\Actions\Sync\WarnOverdueTills;
use Illuminate\Console\Command;

/**
 * Tells owners which paired tills haven't synced for a day (Phase 7). Run
 * hourly by the scheduler, so a warning goes out within the hour.
 */
class WarnOverdueTillsCommand extends Command
{
    protected $signature = 'sync:warn-overdue';

    protected $description = 'Warn owners about paired tills that have not synced for a day';

    public function handle(WarnOverdueTills $warn): int
    {
        $this->info('Warned about '.$warn->execute().' till(s).');

        return self::SUCCESS;
    }
}
