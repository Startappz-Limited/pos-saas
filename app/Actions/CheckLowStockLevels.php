<?php

namespace App\Actions;

use App\Enums\AlertStatus;
use App\Models\LowStockAlert;
use App\Models\Shop;
use App\Services\Stock\LowStockQuery;
use Illuminate\Support\Collection;

class CheckLowStockLevels
{
    public function __construct(
        protected CreateLowStockAlert $createAlert
    ) {}

    /**
     * Raises an alert for every item at or below its reorder level, and resolves the open alerts of items that
     * have recovered (stage 11c, step 2): "Stock recovered", with no user named. A check with no user (the
     * scheduler, a stock change) is the system's; one run by a person records that person.
     *
     * The levels come from LowStockQuery, the one definition of low stock (sellable stock, base units).
     */
    public function execute(int $shopId, ?int $userId = null): Collection
    {
        // A shop outside the current business is not found, so it has no alerts (as the shop-scoped query had none).
        $shop = Shop::query()->find($shopId);
        if ($shop === null) {
            return collect();
        }

        $low = app(LowStockQuery::class)->forShop($shop);

        $alerts = $low->map(fn (array $row) => $this->createAlert->execute([
            'shop_id' => $shopId,
            'product_id' => $row['product_id'],
            'variation_id' => $row['is_default'] ? null : $row['variation_id'],
            'current_quantity' => $row['sellable'],
            'threshold_quantity' => $row['threshold'],
            'created_by' => $userId,
        ]));

        $this->resolveRecovered($shopId, $low);

        return $alerts;
    }

    /**
     * An open alert whose item is no longer low is resolved ("Stock recovered"). A new alert opens again if the
     * item drops back: resolved alerts are not reused.
     *
     * @param  Collection<int, array<string, mixed>>  $low
     */
    private function resolveRecovered(int $shopId, Collection $low): void
    {
        $lowKeys = $low->map(fn (array $row): string => $row['product_id'].':'.($row['is_default'] ? '' : $row['variation_id']))->all();

        LowStockAlert::query()
            ->where('shop_id', $shopId)
            ->whereIn('status', [AlertStatus::PENDING, AlertStatus::ACKNOWLEDGED])
            ->get()
            ->each(function (LowStockAlert $alert) use ($lowKeys): void {
                $key = $alert->product_id.':'.($alert->variation_id ?? '');

                if (! in_array($key, $lowKeys, true)) {
                    $alert->resolveRecovered();
                }
            });
    }
}
