<?php

namespace App\Console\Commands;

use App\Actions\Catalogue\CopyCatalogueToUnits;
use App\Console\Concerns\RunsForBusiness;
use Illuminate\Console\Command;

/**
 * Release 1 of stage 11, step 7: copies a business's catalogue into units, shop unit prices and
 * shop variations, and lists every difference before anything is written. Dry run unless --apply;
 * a second --apply changes nothing; nothing is written while a barcode clashes.
 */
class CopyCatalogueToUnitsCommand extends Command
{
    use RunsForBusiness;

    protected $signature = 'catalogue:copy-to-units {--apply : Write the copy (without it, only list the plan)}';

    protected $description = 'Copy each variation into its base unit, prices and shop facts (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(CopyCatalogueToUnits $copy): int
    {
        $apply = (bool) $this->option('apply');
        $report = $copy->run($apply);

        $this->line($apply ? 'Writing the copy.' : 'Dry run: nothing is written. Pass --apply to copy.');
        $this->table(['Item', 'Count'], [
            ['Base units to create', $report['units']],
            ['Shop unit prices to copy', $report['shop_prices']],
            ['Costs to move into shop variations', $report['costs']],
            ['Reorder levels to move', $report['reorder_levels']],
            ['Sale and return lines to link to a unit', $report['lines']],
            ['Returns with no unit to place', $report['unresolved_returns']],
        ]);

        foreach (['Units with a precision not in the list (set to 0)' => $report['precision_unlisted'], 'Barcodes waiting for a unit' => $report['unassigned'], 'Differences (the variation\'s figure is kept)' => $report['differences']] as $title => $lines) {
            if ($lines !== []) {
                $this->warn($title.':');
                foreach ($lines as $line) {
                    $this->line('  '.$line);
                }
            }
        }

        if ($report['clashes'] !== []) {
            $this->error('Barcode clashes stop the copy; nothing was written:');
            foreach ($report['clashes'] as $line) {
                $this->line('  '.$line);
            }

            return self::FAILURE;
        }

        if ($apply) {
            $this->info('Copy applied.');
        }

        return self::SUCCESS;
    }
}
