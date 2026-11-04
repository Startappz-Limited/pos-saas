<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\StockMovementType;
use App\Models\CostAllocation;
use App\Models\Sale;
use App\Services\Stock\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives back the stock of sales voided before stage 10 (step 7): a void then
 * left its lines' allocations in place, so their stock never came back. For
 * every voided sale whose lines still hold allocations it runs
 * StockLedger::release() (`SALE_VOID`). A dry run unless `--apply`; a second
 * `--apply` changes nothing.
 *
 * Stock only: it records no payouts and no refunds owed. Those sales' sessions
 * were counted at the time, so their money stays as it was.
 */
class RestockVoidedSales extends Command
{
    use RunsForBusiness;

    protected $signature = 'sales:restock-voided
        {--apply : Give the stock back (a dry run otherwise)}';

    protected $description = 'Give back the stock of sales voided before voids released it';

    public function handleForBusiness(StockLedger $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $units = 0;

        Sale::query()->where('status', 'voided')
            ->whereHas('items', fn ($items) => $items->whereIn('sale_items.id', DB::table('cost_allocations')
                ->where('consumer_type', CostAllocation::CONSUMER_SALE_ITEM)->select('consumer_id')))
            ->with(['items', 'shop:id,name'])->orderBy('id')
            ->each(function (Sale $sale) use ($apply, $ledger, &$rows, &$units): void {
                DB::transaction(function () use ($sale, $apply, $ledger, &$rows, &$units): void {
                    foreach ($sale->items as $item) {
                        $held = (int) DB::table('cost_allocations')->where('consumer_type', CostAllocation::CONSUMER_SALE_ITEM)
                            ->where('consumer_id', $item->id)->sum('quantity_allocated');
                        if ($held === 0) {
                            continue;
                        }
                        if ($apply) {
                            $ledger->release($item, StockMovementType::SALE_VOID, ['reference' => $sale, 'notes' => "Restocked: sale {$sale->invoice_number} was voided before voids released stock."]);
                        }
                        $rows[] = [$sale->invoice_number, $sale->shop?->name, $item->product?->name ?? $item->product_id, $held];
                        $units += $held;
                    }
                });
            });

        if ($rows === []) {
            $this->info('No voided sale still holds stock.');

            return self::SUCCESS;
        }

        $this->table(['Sale', 'Shop', 'Line', 'Units'], $rows);
        $this->info(($apply ? 'Restocked ' : 'Would restock ').$units.' unit(s). No payouts or refunds owed are recorded: those sales\' sessions were counted at the time.');
        if (! $apply) {
            $this->info('Dry run: nothing was changed. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
