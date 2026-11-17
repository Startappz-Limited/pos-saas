<?php

namespace App\Actions\Stock;

use App\Enums\AdjustmentReason;
use App\Exceptions\BusinessRuleException;
use App\Models\CostLayer;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentService;
use App\Support\Stock\Expiry;
use Illuminate\Support\Collection;

/**
 * "Write off expired" (stage 11, step 5): a stock adjustment with the reason "expired" that
 * takes each expired batch of a shop out of that batch, at its own cost. It is an ordinary
 * adjustment, so it goes through stage 10's approval rule: expiry is a loss reason, so it waits
 * for a second person, and nothing about the stock changes until they approve it.
 *
 * Adjustment lines are whole base units today, so a batch's fraction (a kg batch left with
 * 2.5) is not written off here; {@see self::fractionsLeft()} lists them for the page, and
 * stage 11's "adjustment lines in any unit" takes them.
 */
class WriteOffExpiredStock
{
    public function __construct(private readonly StockAdjustmentService $adjustments) {}

    /**
     * @throws BusinessRuleException when the shop holds no expired stock to write off
     */
    public function execute(Shop $shop, int $userId): StockAdjustment
    {
        $lines = [];

        foreach ($this->expiredLayers($shop) as $layer) {
            $whole = (int) floor((float) $layer->remaining_quantity);

            if ($whole < 1) {
                continue;
            }

            $lines[] = [
                'product_id' => $layer->product_id,
                'variation_id' => $layer->variation_id,
                'quantity_change' => -$whole,
                'cost_layer_id' => $layer->id,
                'item_notes' => __('Batch :batch, expired :date.', [
                    'batch' => $layer->batch_number ?? '—',
                    'date' => $layer->expiry_date->toDateString(),
                ]),
            ];
        }

        if ($lines === []) {
            throw new BusinessRuleException(__('This shop holds no expired stock to write off.'));
        }

        return $this->adjustments->createAdjustment([
            'shop_id' => $shop->id,
            'reason' => AdjustmentReason::EXPIRED->value,
            'notes' => __('Expired stock written off on :date.', ['date' => Expiry::localToday($shop)->toDateString()]),
            'items' => $lines,
        ], $userId);
    }

    /**
     * The shop's batches that hold stock and are expired now, oldest expiry first.
     *
     * @return Collection<int, CostLayer>
     */
    public function expiredLayers(Shop $shop)
    {
        return CostLayer::query()
            ->where('shop_id', $shop->id)
            ->where('remaining_quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', Expiry::firstSellableDate($shop)->toDateString())
            ->orderBy('expiry_date')->orderBy('id')
            ->get();
    }

    /**
     * Expired batches whose remaining quantity has a fraction the write-off can't take yet.
     *
     * @return Collection<int, CostLayer>
     */
    public function fractionsLeft(Shop $shop)
    {
        return $this->expiredLayers($shop)->filter(fn (CostLayer $layer): bool => (float) $layer->remaining_quantity !== floor((float) $layer->remaining_quantity));
    }
}
