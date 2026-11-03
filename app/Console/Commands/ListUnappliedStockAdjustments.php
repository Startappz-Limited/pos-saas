<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\AdjustmentReason;
use App\Models\StockAdjustment;
use App\Services\Stock\StockLedger;
use Illuminate\Console\Command;

/**
 * Read-only (stage 10, step 1g). Adjustments approved before approving applied
 * the stock never changed it; this lists them with their lines and the stock
 * now, so the owner can apply or reject each from its page. Nothing is applied
 * here: the stock may have been corrected another way since.
 *
 * `--reason=<reason>` lists every adjustment with that reason whatever its
 * status instead ("returned_to_supplier", so purchase returns can be raised for
 * any credit the supplier still owes).
 */
class ListUnappliedStockAdjustments extends Command
{
    use RunsForBusiness;

    protected $signature = 'stock-adjustments:list-unapplied
        {--reason= : List every adjustment with this reason, whatever its status}';

    protected $description = 'List stock adjustments approved but never applied (read-only)';

    public function handleForBusiness(StockLedger $ledger): int
    {
        $reason = $this->option('reason');
        if ($reason !== null && AdjustmentReason::tryFrom($reason) === null) {
            $this->error("Unknown reason \"{$reason}\". One of: ".implode(', ', array_column(AdjustmentReason::cases(), 'value')));

            return self::INVALID;
        }

        $adjustments = StockAdjustment::query()
            ->with(['shop', 'creator', 'items.product', 'items.variation'])
            ->when($reason !== null, fn ($query) => $query->where('reason', $reason), fn ($query) => $query->where('status', 'approved'))
            ->orderBy('id')
            ->get();

        if ($adjustments->isEmpty()) {
            $this->info($reason !== null ? "No adjustments with the reason \"{$reason}\"." : 'No adjustments are approved but unapplied.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($adjustments as $adjustment) {
            foreach ($adjustment->items as $item) {
                $rows[] = [
                    $adjustment->adjustment_number,
                    $adjustment->status,
                    $adjustment->reason?->value,
                    $adjustment->shop?->name ?? '-',
                    $adjustment->created_at?->toDateString(),
                    trim(($item->product?->name ?? 'Product deleted').($item->variation && ! $item->variation->is_default ? ' / '.$item->variation->name : '')),
                    $this->signed((int) $item->quantity_change),
                    $item->variation_id ? $ledger->quantity((int) $item->shop_id, (int) $item->variation_id) : '-',
                ];
            }
        }

        $this->table(['Adjustment', 'Status', 'Reason', 'Shop', 'Created', 'Line', 'Change', 'Stock now'], $rows);
        $this->info($adjustments->count().' adjustment(s). Nothing was changed: apply or reject each from its page.');

        return self::SUCCESS;
    }

    private function signed(int $change): string
    {
        return $change > 0 ? '+'.$change : (string) $change;
    }
}
