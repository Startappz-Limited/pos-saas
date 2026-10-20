<?php

namespace App\Console\Commands;

use App\Actions\Billing\SweepSubscriptions;
use Illuminate\Console\Command;

/**
 * Reminds owners before a subscription ends, warns them before a lapsed
 * business is closed, and starts the closure when the grace period is over
 * (Phase 4). Run daily by the scheduler; platform-wide.
 */
class SweepSubscriptionsCommand extends Command
{
    protected $signature = 'billing:sweep';

    protected $description = 'Remind, warn and close businesses by their subscription state';

    public function handle(SweepSubscriptions $sweep): int
    {
        $result = $sweep->execute();

        $this->info("Reminded {$result['reminded']}, warned {$result['warned']}, closed {$result['closed']}.");

        return self::SUCCESS;
    }
}
