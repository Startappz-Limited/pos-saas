<?php

namespace App\Actions\Stock;

use App\Models\InventorySnapshot;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;

/**
 * Retention (stage 11c, step 3): a `daily` snapshot older than 13 months is deleted. `month_end` and `manual`
 * snapshots are kept indefinitely.
 */
class PruneDailySnapshots
{
    public const RETAIN_MONTHS = 13;

    public function __construct(private readonly TenantContext $tenant) {}

    public function execute(?CarbonInterface $now = null): int
    {
        $cutoff = ($now ?? now())->copy()->subMonths(self::RETAIN_MONTHS)->toDateString();
        $deleted = 0;

        $this->tenant->eachBusiness(function () use ($cutoff, &$deleted): void {
            $deleted += InventorySnapshot::query()->dailyBefore($cutoff)->delete();
        });

        return $deleted;
    }
}
