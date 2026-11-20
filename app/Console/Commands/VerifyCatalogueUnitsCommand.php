<?php

namespace App\Console\Commands;

use App\Actions\Catalogue\VerifyCatalogueUnits;
use App\Console\Concerns\RunsForBusiness;
use Illuminate\Console\Command;

/**
 * Read-only check that release 2 can drop the old catalogue columns (stage 11, step 7). Exits
 * non-zero on a gap; warnings (barcodes waiting for assignment) don't fail it.
 */
class VerifyCatalogueUnitsCommand extends Command
{
    use RunsForBusiness;

    protected $signature = 'catalogue:verify-units';

    protected $description = 'Check every catalogue value has its new home before the old columns are dropped (read-only).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(VerifyCatalogueUnits $verify): int
    {
        $result = $verify->run();

        $this->table(['Check', 'Count'], collect($result['counts'])->map(fn (int $count, string $check): array => [$check, $count])->values()->all());

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($result['gaps'] as $gap) {
            $this->error($gap);
        }

        if ($result['gaps'] !== []) {
            return self::FAILURE;
        }

        $this->info('Every catalogue value has its new home.');

        return self::SUCCESS;
    }
}
