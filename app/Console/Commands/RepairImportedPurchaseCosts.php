<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Clears purchase costs that were never purchase costs.
 *
 * The WooCommerce and Shopify importers used to write a SELLING price into
 * `cost_price` (`regular_price` and `compare_at_price` respectively). Because
 * both sit at or above what the customer actually pays, every affected product
 * reported a loss on every sale. Those values carry no information worth
 * keeping, so this resets them to NULL — an honest "cost unknown" that the
 * purchase-cost screen can then surface for manual entry.
 */
class RepairImportedPurchaseCosts extends Command
{
    use ConfirmableTrait;

    protected $signature = 'products:repair-purchase-cost
                            {--apply : Write the changes (defaults to a dry run)}
                            {--strict : Only clear costs strictly above the selling price, keeping zero-margin ones}
                            {--shop= : Restrict to products attached to this shop id}
                            {--force : Skip the confirmation prompt in production}';

    protected $description = 'Clear purchase costs that were derived from a selling price by the e-commerce importers';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $operator = $this->option('strict') ? '>' : '>=';
        $shopId = $this->option('shop') ? (int) $this->option('shop') : null;

        $products = $this->suspectProducts($operator, $shopId)->get();
        $variations = $this->suspectVariations($operator)->get();

        if ($products->isEmpty() && $variations->isEmpty()) {
            $this->info('No products with a selling-price-derived purchase cost were found.');

            return self::SUCCESS;
        }

        $this->table(
            ['SKU', 'Product', 'Cost', 'Selling', 'Margin'],
            $products->take(20)->map(fn (Product $product) => [
                $product->sku,
                str($product->name)->limit(45)->value(),
                number_format((float) $product->cost_price, 2),
                number_format((float) $product->selling_price, 2),
                number_format((float) $product->selling_price - (float) $product->cost_price, 2),
            ])->all()
        );

        if ($products->count() > 20) {
            $this->line(sprintf('  … and %d more products.', $products->count() - 20));
        }

        $this->newLine();
        $this->warn(sprintf(
            '%d product(s) and %d variation(s) have a purchase cost %s their selling price.',
            $products->count(),
            $variations->count(),
            $operator === '>' ? 'above' : 'at or above',
        ));

        if (! $apply) {
            $this->newLine();
            $this->info('Dry run — nothing was changed. Re-run with --apply to clear these costs.');

            return self::SUCCESS;
        }

        if (! $this->confirmToProceed(sprintf(
            'Clearing the purchase cost on %d record(s)',
            $products->count() + $variations->count(),
        ))) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($products, $variations): void {
            // Saved individually so the Auditable trait records each change;
            // a bulk update would leave no trail of what the cost used to be.
            foreach ($products as $product) {
                $product->cost_price = null;
                $product->save();
            }

            foreach ($variations as $variation) {
                $variation->cost_price = null;
                $variation->save();
            }
        });

        $this->info(sprintf(
            'Cleared the purchase cost on %d product(s) and %d variation(s).',
            $products->count(),
            $variations->count(),
        ));
        $this->line('Next: enter the real costs under Products → Purchase Costs, then run `php artisan sales:recompute-profit`.');

        return self::SUCCESS;
    }

    /**
     * @return Builder<Product>
     */
    private function suspectProducts(string $operator, ?int $shopId)
    {
        return Product::query()
            ->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->whereColumn('cost_price', $operator, 'selling_price')
            ->when($shopId, fn ($query) => $query->whereHas(
                'shops', fn ($q) => $q->where('shops.id', $shopId)
            ))
            ->orderBy('sku');
    }

    /**
     * @return Builder<ProductVariation>
     */
    private function suspectVariations(string $operator)
    {
        return ProductVariation::query()
            ->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->whereColumn('cost_price', $operator, 'selling_price')
            ->orderBy('sku');
    }
}
