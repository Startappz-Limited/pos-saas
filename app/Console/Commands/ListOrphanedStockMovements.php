<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\StockMovementType;
use App\Models\Sale;
use App\Models\StockMovement;
use Illuminate\Console\Command;

/**
 * Read-only (stage 10, step 7): sale movements whose sale row no longer
 * exists, from sales hard-deleted before this stage. Their allocations are
 * gone, so nothing can be released automatically: the business corrects that
 * stock by hand. Sales deleted since are soft-deleted and are never listed.
 */
class ListOrphanedStockMovements extends Command
{
    use RunsForBusiness;

    protected $signature = 'stock:orphaned-movements';

    protected $description = 'List sale stock movements whose sale no longer exists (read-only)';

    public function handleForBusiness(): int
    {
        $known = Sale::query()->withTrashed()->select('uuid');
        $movements = StockMovement::query()
            ->where('movement_type', StockMovementType::SALE->value)
            ->where('reference_type', Sale::class)
            ->whereNotIn('reference_uuid', $known)
            ->with(['shop:id,name', 'product:id,name'])->orderBy('id')->get();

        if ($movements->isEmpty()) {
            $this->info('No sale movement has lost its sale.');

            return self::SUCCESS;
        }

        $this->table(['When', 'Shop', 'Product', 'Units', 'Sale (gone)'], $movements->map(fn (StockMovement $movement): array => [
            $movement->created_at?->toDateTimeString(), $movement->shop?->name, $movement->product?->name, $movement->quantity, $movement->reference_uuid,
        ])->all());
        $this->info($movements->count().' movement(s). Nothing was changed: correct that stock by hand (a stock adjustment).');

        return self::SUCCESS;
    }
}
