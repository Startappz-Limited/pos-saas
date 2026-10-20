<?php

namespace App\Console\Commands;

use App\Actions\Business\CloseBusiness;
use App\Models\BusinessClosure;
use Illuminate\Console\Command;
use Throwable;

/**
 * Erases every business whose closure grace period is over (stage 3). Run
 * daily by the scheduler. Platform-wide: it acts on closing businesses,
 * which the per-business sweeps skip.
 */
class EraseClosedBusinesses extends Command
{
    protected $signature = 'businesses:erase-closed
        {--dry-run : List the businesses that would be erased, and erase nothing}';

    protected $description = 'Erase the data of businesses whose closure grace period has ended';

    public function handle(CloseBusiness $closer): int
    {
        $due = BusinessClosure::query()->open()->where('delete_after', '<=', now())->orderBy('delete_after')->get();

        if ($due->isEmpty()) {
            $this->info('No closed business is due for erasure.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($due as $closure) {
            if ($this->option('dry-run')) {
                $this->line("Would erase {$closure->business_name} ({$closure->business_uuid}), closed {$closure->created_at}.");

                continue;
            }

            try {
                $closer->erase($closure);
                $this->info("Erased {$closure->business_name} ({$closure->business_uuid}).");
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error("Could not erase {$closure->business_name}: {$exception->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
