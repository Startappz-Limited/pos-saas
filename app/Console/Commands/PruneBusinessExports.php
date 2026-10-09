<?php

namespace App\Console\Commands;

use App\Enums\BusinessExportStatus;
use App\Models\BusinessExport;
use Illuminate\Console\Command;

/**
 * Deletes the files of expired business exports. Each one holds every
 * customer's and staff member's details, so it must not outlive its purpose.
 * The row stays (status expired) as the record that the export happened.
 * Runs daily from routes/console.php.
 */
class PruneBusinessExports extends Command
{
    protected $signature = 'business-exports:prune';

    protected $description = 'Delete the files of expired business data exports';

    public function handle(): int
    {
        $count = 0;

        BusinessExport::query()->expired()->each(function (BusinessExport $export) use (&$count): void {
            $export->deleteFile();
            $export->update(['status' => BusinessExportStatus::EXPIRED, 'path' => null]);
            $count++;
        });

        $this->info("Expired {$count} export(s).");

        return self::SUCCESS;
    }
}
