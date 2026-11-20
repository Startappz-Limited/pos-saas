<?php

namespace App\Jobs;

use App\Actions\CheckLowStockLevels;
use App\Actions\Stock\SyncExpiringStockAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Re-checks one shop's low-stock and expiring-stock alerts (stage 11c, step 2), so an alert appears or clears
 * when stock changes, not when someone presses "Check levels". Queued by the stock ledger when a change crosses
 * a reorder level, and by the expiry touch; unique per shop while waiting, so a burst of sales runs it once.
 *
 * A system run: no user is named on what it raises or resolves.
 */
class CheckShopLowStockJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $shopId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->shopId;
    }

    public function handle(CheckLowStockLevels $lowStock, SyncExpiringStockAlerts $expiring): void
    {
        $lowStock->execute($this->shopId, null);
        $expiring->execute($this->shopId);
    }
}
