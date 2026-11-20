<?php

namespace App\Actions\Stock;

use App\Jobs\CheckShopLowStockJob;
use App\Jobs\PushShopStockJob;
use App\Models\CostLayer;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use App\Support\Stock\Expiry;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The expiry touch (stage 11, step 5): when a shop's batch expires, nothing writes its stock
 * row, so a till pulling by `updated_at` would never learn the stock is no longer sellable.
 * This touches the `shop_variation` row of every variation that holds a batch which has
 * expired since the shop's last run, and asks the online stores to be pushed their sellable
 * stock again.
 *
 * Each shop keeps the time it was last done (`shops.expiry_touched_at`), so a missed midnight
 * (the scheduler down, a slow run) is caught up by the next run, two runs in a row touch
 * nothing new, and a shop in another timezone is touched at its own midnight. A shop never
 * run before (NULL) touches every row with an expired batch once. Expiry itself is judged only
 * by {@see Expiry}, as `consume()` and the pull do.
 */
class TouchExpiredStock
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly StockLedger $ledger,
    ) {}

    /**
     * Runs for every active business; returns how many stock rows were touched.
     */
    public function execute(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $touched = 0;

        $this->tenant->eachBusiness(function () use ($now, &$touched): void {
            foreach (Shop::query()->get() as $shop) {
                $touched += $this->touchShop($shop, $now);
            }
        });

        return $touched;
    }

    /**
     * One shop: its touches and its "last done" time move together, in one transaction.
     */
    public function touchShop(Shop $shop, CarbonInterface $now): int
    {
        [$after, $upTo] = Expiry::expiredDateRange($shop, $shop->expiry_touched_at, $now);

        $variations = CostLayer::query()
            ->where('shop_id', $shop->id)
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->when($after !== null, fn ($query) => $query->where('expiry_date', '>', $after))
            ->where('expiry_date', '<=', $upTo)
            ->distinct()
            ->get(['variation_id', 'product_id']);

        DB::transaction(function () use ($shop, $now, $variations): void {
            foreach ($variations as $layer) {
                $this->ledger->touch($shop->id, (int) $layer->variation_id);
            }

            // Quietly: a bookkeeping timestamp written every run is not an audited change.
            $shop->forceFill(['expiry_touched_at' => $now])->saveQuietly();
        });

        // The stores are sent sellable stock, so they stop offering what expired.
        foreach ($variations->pluck('product_id')->unique() as $productId) {
            PushShopStockJob::dispatch($shop->id, (int) $productId);
        }

        // Batches that expired or came into the window raise or move their expiry alerts, and low stock is re-judged.
        CheckShopLowStockJob::dispatch($shop->id);

        return $variations->count();
    }
}
