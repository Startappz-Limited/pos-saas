<?php

namespace App\Actions;

use App\Models\Shop;
use App\Services\Stock\LowStockQuery;
use Illuminate\Support\Collection;

class CheckLowStockLevels
{
    public function __construct(
        protected CreateLowStockAlert $createAlert
    ) {}

    /**
     * Checks the shop's stock and raises an alert for every item at or below
     * its reorder level (stage 1b: per-shop stock).
     *
     * A row's own reorder level (shop_variation) wins, else its product's. A
     * simple product is checked through its default variation and alerted
     * without a variation, as before; a product with variations is checked per
     * variation. Only this shop's stock counts.
     */
    public function execute(int $shopId, ?int $userId = null): Collection
    {
        // The one low-stock definition (LowStockQuery): sellable stock, in base units.
        // A shop outside the current business is not found, so it has no alerts (as the shop-scoped query had none).
        $shop = Shop::query()->find($shopId);
        if ($shop === null) {
            return collect();
        }

        $low = app(LowStockQuery::class)->forShop($shop);

        return $low->map(fn (array $row) => $this->createAlert->execute([
            'shop_id' => $shopId,
            'product_id' => $row['product_id'],
            'variation_id' => $row['is_default'] ? null : $row['variation_id'],
            'current_quantity' => $row['sellable'],
            'threshold_quantity' => $row['threshold'],
            // No user (a scheduled check) records no creator, never user 1, who may be another business's.
            'created_by' => $userId,
        ]));
    }
}
