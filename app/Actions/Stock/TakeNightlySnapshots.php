<?php

namespace App\Actions\Stock;

use App\Actions\CreateInventorySnapshot;
use App\Models\InventorySnapshot;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The nightly snapshot (stage 11c, step 3): once a shop's local midnight has passed since its last one, the day that
 * ended is snapshotted, dated that day. A shop never snapshotted before gets yesterday's. The last-taken time moves
 * with the snapshot (`shops.snapshot_taken_at`), so two runs in one day take nothing new.
 *
 * The last day of a month is a `month_end` snapshot, kept indefinitely. Days the scheduler missed further back are
 * not taken here: they are rebuilt from the ledger (stage 11c, step 4).
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
            foreach (Shop::query()->get() as $shop) {
                $taken += $this->takeForShop($shop, $now);
            }
        });

        return $taken;
    }

    public function takeForShop(Shop $shop, CarbonInterface $now): int
    {
        $zone = $shop->localTimezone();
        $today = CarbonImmutable::instance($now)->setTimezone($zone)->startOfDay();
        // Read from the stored string and taken as UTC (the time is saved in UTC below), so the cast's zone can't shift it.
        $stored = $shop->getRawOriginal('snapshot_taken_at');
        $lastTaken = $stored !== null
            ? CarbonImmutable::parse($stored, 'UTC')->setTimezone($zone)->startOfDay()
            : null;

        if ($lastTaken !== null && $lastTaken->greaterThanOrEqualTo($today)) {
            return 0;
        }

        $day = $today->subDay();
        $kind = $day->isLastOfMonth() ? InventorySnapshot::KIND_MONTH_END : InventorySnapshot::KIND_DAILY;

        $count = $this->snapshots->execute($shop->id, $day->toDateString(), null, $kind, InventorySnapshot::SOURCE_ON_TIME)->count();

        // Stored in UTC: a datetime is saved in the wall-clock time of the instance it's given, so an instant in
        // another zone would read back shifted. Quietly: the bookkeeping time is not an audited change.
        $shop->forceFill(['snapshot_taken_at' => CarbonImmutable::instance($now)->utc()])->saveQuietly();

        return $count;
    }
}
