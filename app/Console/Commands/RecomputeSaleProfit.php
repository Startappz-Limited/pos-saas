<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Re-derives cost and profit on historic sales from current product costs.
 *
 * COGS is snapshotted onto `sale_items.unit_cost` at the moment of sale, so
 * sales made while a product carried an importer-derived cost kept that wrong
 * figure forever — which is what produced negative profit in reporting. Once
 * the real purchase costs are in place, this replays the same arithmetic the
 * sale path uses and rewrites the snapshot.
 *
 * Items whose product still has no purchase cost are left untouched rather
 * than zeroed, so running this early cannot quietly overstate profit.
 */
class RecomputeSaleProfit extends Command
{
    protected $signature = 'sales:recompute-profit
                            {--apply : Write the changes (defaults to a dry run)}
                            {--sale= : Restrict to a single sale uuid}
                            {--from= : Only sales completed on or after this date (Y-m-d)}
                            {--to= : Only sales completed on or before this date (Y-m-d)}
                            {--zero-unknown : Treat a still-unknown purchase cost as 0 instead of skipping the sale}';

    protected $description = 'Recompute cost and profit on existing sales from the current product purchase costs';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $zeroUnknown = (bool) $this->option('zero-unknown');

        $recalculated = 0;
        $unchanged = 0;
        $skipped = 0;
        $profitBefore = 0.0;
        $profitAfter = 0.0;

        $this->baseQuery()->chunkById(200, function ($sales) use (
            $apply, $zeroUnknown, &$recalculated, &$unchanged, &$skipped, &$profitBefore, &$profitAfter
        ): void {
            foreach ($sales as $sale) {
                $outcome = $this->recalculate($sale, $zeroUnknown, $apply);

                if ($outcome === null) {
                    $skipped++;

                    continue;
                }

                $profitBefore += $outcome['profit_before'];
                $profitAfter += $outcome['profit_after'];

                $outcome['changed'] ? $recalculated++ : $unchanged++;
            }
        });

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Sales recalculated', number_format($recalculated)],
            ['Sales already correct', number_format($unchanged)],
            ['Sales skipped (cost still unknown)', number_format($skipped)],
            ['Total profit before', number_format($profitBefore, 2)],
            ['Total profit after', number_format($profitAfter, 2)],
            ['Difference', number_format($profitAfter - $profitBefore, 2)],
        ]);

        if ($skipped > 0) {
            $this->warn(sprintf(
                '%d sale(s) were skipped because at least one product still has no purchase cost. '
                .'Enter those costs under Products → Purchase Costs and re-run.',
                $skipped,
            ));
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Dry run — nothing was changed. Re-run with --apply to write these figures.');
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<Sale>
     */
    private function baseQuery()
    {
        return Sale::query()
            ->with(['items.product:id,cost_price', 'items.variation:id,cost_price'])
            ->when($this->option('sale'), fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->when($this->option('from'), fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($this->option('to'), fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'));
    }

    /**
     * Returns null when the sale must be skipped because a cost is unknown.
     *
     * @return array{changed: bool, profit_before: float, profit_after: float}|null
     */
    private function recalculate(Sale $sale, bool $zeroUnknown, bool $apply): ?array
    {
        $lines = [];
        $totalCost = 0.0;

        foreach ($sale->items as $item) {
            $source = $item->variation ?? $item->product;
            $cost = $source?->cost_price;

            if ($cost === null || (float) $cost <= 0) {
                if (! $zeroUnknown) {
                    return null;
                }

                $cost = 0.0;
            }

            $unitCost = round((float) $cost, 2);
            $lineCost = round($item->quantity * $unitCost, 2);

            // Net revenue for the line. Sales priced VAT-inclusive carry the tax
            // inside `line_total`, so profit is measured against the taxable
            // amount; rows written before the VAT engine existed have no taxable
            // amount recorded and fall back to the line total, which for them is
            // already net because no VAT was ever charged.
            $lineNet = (float) $item->taxable_amount > 0
                ? (float) $item->taxable_amount
                : (float) $item->line_total;

            $profit = round($lineNet - $lineCost, 2);

            $lines[] = [
                'item' => $item,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCost,
                'profit' => $profit,
                'profit_margin' => $lineNet > 0 ? round(($profit / $lineNet) * 100, 2) : 0.0,
            ];

            $totalCost += $lineCost;
        }

        // Mirrors the sale path: profit is measured against the whole sale total
        // (fees and discount included), not just the sum of line profits — but
        // net of VAT. Tax is collected on the revenue authority's behalf and was
        // previously booked as margin, overstating profit on every taxed sale.
        $totalCost = round($totalCost, 2);
        $totalProfit = round((float) $sale->total_amount - (float) $sale->tax_amount - $totalCost, 2);

        $profitBefore = (float) $sale->total_profit;
        $changed = abs($profitBefore - $totalProfit) >= 0.01
            || abs((float) $sale->total_cost - $totalCost) >= 0.01;

        if ($changed && $apply) {
            DB::transaction(function () use ($sale, $lines, $totalCost, $totalProfit): void {
                foreach ($lines as $line) {
                    /** @var SaleItem $item */
                    $item = $line['item'];
                    $item->forceFill([
                        'unit_cost' => $line['unit_cost'],
                        'total_cost' => $line['total_cost'],
                        'profit' => $line['profit'],
                        'profit_margin' => $line['profit_margin'],
                    ])->save();
                }

                $sale->forceFill([
                    'total_cost' => $totalCost,
                    'total_profit' => $totalProfit,
                ])->save();
            });
        }

        return [
            'changed' => $changed,
            'profit_before' => $profitBefore,
            'profit_after' => $totalProfit,
        ];
    }
}
