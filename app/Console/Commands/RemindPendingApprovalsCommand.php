<?php

namespace App\Console\Commands;

use App\Actions\Approvals\RemindPendingApprovals;
use Illuminate\Console\Command;

/**
 * Reminds approvers once about requests pending for 24 hours (stage 10). Run
 * hourly by the scheduler.
 */
class RemindPendingApprovalsCommand extends Command
{
    protected $signature = 'approvals:remind';

    protected $description = 'Remind approvers once about requests pending for over 24 hours';

    public function handle(RemindPendingApprovals $remind): int
    {
        $this->info('Reminded about '.$remind->execute().' request(s).');

        return self::SUCCESS;
    }
}
