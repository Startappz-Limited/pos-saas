<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AssignProductShopFromSales extends Command
{
    protected $signature = 'products:assign-shop-from-sales
        {--dry-run : Preview the changes without writing anything}
        {--reattribute-items : After assigning, re-stamp sale_items.shop_id from each product\'s resolved shop}
        {--include-voided : Include voided sales when tallying which shop sold each product}
        {--force : Skip the confirmation prompt (for non-interactive/production runs)}';

    protected $description = 'Set each product\'s owning shop based on which shop its past sales were made under (the mode of sales.shop_id across the product\'s sale lines).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Tally, per product, how many sale lines were sold under each shop,
        // plus the most recent sale date for tie-breaking.
        $tallyQuery = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->whereNotNull('sales.shop_id')
            ->selectRaw('sale_items.product_id')
            ->selectRaw('sales.shop_id')
            ->selectRaw('COUNT(*) as line_count')
            ->selectRaw('MAX(sales.created_at) as last_sold_at')
            ->groupBy('sale_items.product_id', 'sales.shop_id');

        if (! $this->option('include-voided')) {
            $tallyQuery->where('sales.status', '!=', 'voided');
        }

        $tally = $tallyQuery->get()->groupBy('product_id');

        if ($tally->isEmpty()) {
            $this->warn('No sales history found — nothing to derive from.');

            return self::SUCCESS;
        }

        $shopNames = Shop::query()->pluck('name', 'id');
        $products = Product::query()
            ->whereIn('id', $tally->keys())
            ->get(['id', 'name', 'shop_id'])
            ->keyBy('id');

        $changes = [];

        foreach ($tally as $productId => $rows) {
            $product = $products->get($productId);

            if (! $product) {
                continue; // product hard-deleted; skip
            }

            // Winning shop = most sale lines, then most recent sale, then lowest id.
            $winner = $rows
                ->sortBy([
                    ['line_count', 'desc'],
                    ['last_sold_at', 'desc'],
                    ['shop_id', 'asc'],
                ])
                ->first();

            $resolvedShopId = (int) $winner->shop_id;

            if ((int) $product->shop_id === $resolvedShopId) {
                continue; // already correct
            }

            $changes[] = [
                'product_id' => $productId,
                'name' => $product->name,
                'from' => $product->shop_id ? ($shopNames[$product->shop_id] ?? "#{$product->shop_id}") : '—',
                'to' => $shopNames[$resolvedShopId] ?? "#{$resolvedShopId}",
                'basis' => $rows->map(fn ($r) => ($shopNames[$r->shop_id] ?? "#{$r->shop_id}").'='.$r->line_count)->implode(', '),
                'resolved_shop_id' => $resolvedShopId,
            ];
        }

        $this->info(sprintf(
            '%d product(s) have sales history; %d would change owning shop.',
            $tally->count(),
            count($changes)
        ));

        if ($changes === []) {
            $this->line('Every product with sales is already attributed correctly.');

            return self::SUCCESS;
        }

        $this->table(
            ['Product', 'Name', 'From', 'To', 'Sales basis'],
            array_map(fn ($c) => [
                $c['product_id'], $c['name'], $c['from'], $c['to'], $c['basis'],
            ], $changes)
        );

        if ($dryRun) {
            $this->comment('Dry run — no changes written. Re-run without --dry-run to apply.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Apply these owning-shop assignments?', true)) {
            $this->comment('Aborted — no changes written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes): void {
            foreach ($changes as $change) {
                Product::query()
                    ->where('id', $change['product_id'])
                    ->update(['shop_id' => $change['resolved_shop_id']]);
            }
        });

        $this->info(sprintf('Updated owning shop on %d product(s).', count($changes)));

        if ($this->option('reattribute-items')) {
            $affected = DB::table('sale_items')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('products')
                        ->whereColumn('products.id', 'sale_items.product_id')
                        ->whereNotNull('products.shop_id');
                })
                ->update([
                    'shop_id' => DB::raw('(SELECT products.shop_id FROM products WHERE products.id = sale_items.product_id)'),
                ]);

            $this->info(sprintf('Re-attributed %d sale line(s) from their product\'s shop.', $affected));
        }

        return self::SUCCESS;
    }
}
