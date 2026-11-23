<?php

namespace App\Actions\Stock;

use App\Actions\CreateInventorySnapshot;
use App\Models\Business;
use App\Models\CostLayer;
use App\Models\InventorySnapshot;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The nightly snapshot (stage 11c, steps 3 and 4): once a shop's local midnight has passed since its last run, every day
 * since that run is snapshotted, each as it stood at the end of that day (its local midnight).
 *
 *  - the day that ended is `on_time`; any day before it that the scheduler missed is `rebuilt`, from the ledger;
 *  - a day before the business's ledger began, or whose figures the ledger can't rebuild exactly, takes the current
 *    figures and is `late`;
 *  - a shop never snapshotted before gets yesterday's only (its history is not taken);
 *  - the last day of a month is a `month_end` snapshot, kept indefinitely.
 *
 * The run's time is stored in UTC (`shops.snapshot_taken_at`), so two runs in one day take nothing new.
 */
class TakeNightlySnapshots
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CreateInventorySnapshot $snapshots,
    ) {}

    /**
     * Runs for every active business; returns how many snapshot rows were taken.
     */
    public function execute(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $taken = 0;

        $this->tenant->eachBusiness(function () use ($now, &$taken): void {
            $ledgerStart = $this->ledgerStart();

            foreach (Shop::query()->get() as $shop) {
                $taken += $this->takeForShop($shop, $now, $ledgerStart);
            }
        });

        return $taken;
    }

    public function takeForShop(Shop $shop, CarbonInterface $now, ?CarbonImmutable $ledgerStart = null): int
    {
        $zone = $shop->localTimezone();
        $today = CarbonImmutable::instance($now)->setTimezone($zone)->startOfDay();
        $yesterday = $today->subDay();

        // The stored time is UTC (saved so below): read it as UTC, whatever zone a cast would give it.
        $stored = $shop->getRawOriginal('snapshot_taken_at');

        if ($stored === null) {
            $days = [$yesterday];
        } else {
            // A run on day X took day X-1, so day X and every day after it up to yesterday are still due.
            $lastRunDay = CarbonImmutable::parse($stored, 'UTC')->setTimezone($zone)->startOfDay();
            if ($lastRunDay->greaterThanOrEqualTo($today)) {
                return 0;
            }

            $days = [];
            for ($day = $lastRunDay; $day->lessThanOrEqualTo($yesterday); $day = $day->addDay()) {
                $days[] = $day;
            }
        }

        $count = 0;
        foreach ($days as $day) {
            $kind = $day->isLastOfMonth() ? InventorySnapshot::KIND_MONTH_END : InventorySnapshot::KIND_DAILY;
            // The figures at the day's end: the local midnight that starts the next day.
            $endOfDay = $day->addDay();

            if ($ledgerStart === null || $endOfDay->lessThan($ledgerStart)) {
                $source = InventorySnapshot::SOURCE_LATE;
                $at = null;
            } else {
                $source = $day->equalTo($yesterday) ? InventorySnapshot::SOURCE_ON_TIME : InventorySnapshot::SOURCE_REBUILT;
                $at = $endOfDay;
            }

            $count += $this->snapshots->execute($shop->id, $day->toDateString(), null, $kind, $source, $at)->count();
        }

        // Stored in UTC: a datetime is saved in the wall-clock time of the instance it's given. Quietly: the
        // bookkeeping time is not an audited change.
        $shop->forceFill(['snapshot_taken_at' => CarbonImmutable::instance($now)->utc()])->saveQuietly();

        return $count;
    }

    /**
     * When the current business's ledger began (`businesses.ledger_started_at`, or until `stock:ledger-start` has
     * run, its first batch's time); null when it has no batch yet. Before it, no day can be rebuilt.
     */
    public function ledgerStart(): ?CarbonImmutable
    {
        $business = $this->tenant->business();
        $stored = $business instanceof Business ? $business->getRawOriginal('ledger_started_at') : null;

        if ($stored !== null) {
            return CarbonImmutable::parse($stored, 'UTC');
        }

        $first = CostLayer::query()->min('created_at');

        return $first === null ? null : CarbonImmutable::parse($first, 'UTC');
    }
}
