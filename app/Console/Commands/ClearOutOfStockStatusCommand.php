<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Retires the `out_of_stock` product status (stage 11, step 6). Stock is a
 * fact of the ledger, not a product status: a product with no stock is simply
 * out of stock at its shop. Rows with the status move to `inactive`, which
 * hides them from online stores exactly as the old status did. Dry run by
 * default; a second --apply changes nothing.
 */
class ClearOutOfStockStatusCommand extends Command
{
    use RunsForBusiness;

    protected $signature = 'products:clear-out-of-stock-status
        {--apply : Write the change (without it, only count)}';

    protected $description = 'Move products with the retired out_of_stock status to inactive (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(): int
    {
        // Runs inside the business (RunsForBusiness), so the tenant scopes limit the query to it.
        $query = Product::query()->where('status', ProductStatus::OUT_OF_STOCK->value);

        $count = (clone $query)->count();
        $apply = (bool) $this->option('apply');

        $this->info("{$count} product(s) with status out_of_stock".($apply ? '' : ' (dry run)').'.');

        if ($apply && $count > 0) {
            $updated = $query->update(['status' => ProductStatus::INACTIVE->value, 'updated_at' => now()]);
            $this->info("Moved {$updated} product(s) to inactive.");
        }

        return self::SUCCESS;
    }
}
