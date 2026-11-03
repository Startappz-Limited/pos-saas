<?php

namespace App\Support\Stock;

use App\Models\Business;
use App\Models\ProductVariation;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\Stock\StockLedger;

/**
 * Who applies a stock adjustment (decided 27 and 28 October 2026, stage 10,
 * step 1g). Its creator applies it on saving, unless it needs a second person:
 * any line's reason is loss, theft, damage or expiry; any line's cost is
 * unknown; or its cost value is at or above the business's threshold. A second
 * person is someone other than the creator holding `stock-adjustments.approve`.
 *
 * The cost value is Σ |quantity| × unit cost over the lines, so a recount of
 * +100 and −100 counts 200 units: a decrease at the cost the ledger would
 * consume now, an increase at the cost it would be received at (the shop's
 * latest batch cost of the variation, else unknown).
 */
final class AdjustmentApproval
{
    public const DEFAULT_THRESHOLD = 5000.0;

    public function __construct(private StockLedger $ledger) {}

    public static function threshold(?Business $business): float
    {
        $value = data_get($business?->settings, 'stock.adjustment_approval_threshold');

        return is_numeric($value) ? (float) $value : self::DEFAULT_THRESHOLD;
    }

    public function needsSecondPerson(StockAdjustment $adjustment): bool
    {
        if ($adjustment->reason?->needsSecondPerson()) {
            return true;
        }

        $value = 0.0;
        foreach ($adjustment->items as $item) {
            $cost = $this->unitCost($item);
            if ($cost === null) {
                return true;
            }
            $value += abs((int) $item->quantity_change) * $cost;
        }

        return round($value, 2) >= self::threshold($adjustment->shop?->business);
    }

    /**
     * The adjustment's cost value; unknown costs count as zero.
     */
    public function value(StockAdjustment $adjustment): float
    {
        return round($adjustment->items->sum(fn (StockAdjustmentItem $item): float => abs((int) $item->quantity_change) * (float) $this->unitCost($item)), 2);
    }

    /**
     * The unit cost a line moves stock at; null when unknown.
     */
    public function unitCost(StockAdjustmentItem $item): ?float
    {
        $variation = $this->variationOf($item);
        $shopId = (int) $item->shop_id;

        return (int) $item->quantity_change < 0
            ? $this->ledger->costToConsume($shopId, $variation, (int) $item->quantity_change)
            : $this->ledger->latestBatchCost($shopId, $variation);
    }

    public function variationOf(StockAdjustmentItem $item): ProductVariation
    {
        return $item->variation ?? $item->product->defaultVariation()->firstOrFail();
    }
}
