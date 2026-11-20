<?php

namespace App\Console\Commands;

use App\Actions\Stock\TouchExpiredStock;
use Illuminate\Console\Command;

/**
 * Touches the stock rows whose batches have expired since each shop's last run, so tills and
 * online stores learn of an expiry (stage 11, step 5). Scheduled every 15 minutes: each shop
 * is done once its own local midnight has passed since its last run.
 */
class TouchExpiringStockCommand extends Command
{
    protected $signature = 'stock:touch-expiring';

    protected $description = 'Touch the stock of batches that expired since each shop\'s last run, so tills and stores pull it again';

    public function handle(TouchExpiredStock $touch): int
    {
        $this->info('Touched '.$touch->execute().' stock row(s).');

        return self::SUCCESS;
    }
}
