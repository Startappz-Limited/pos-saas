<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductEcommerceSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillShopProductPivot extends Command
{
    protected $signature = 'products:backfill-shop-pivot';

    protected $description = 'Backfill shop_product pivot table from existing ecommerce sync records';

    public function handle(): int
    {
        $syncRecords = ProductEcommerceSync::where('sync_direction', 'from_platform')->get();

        $this->info("Found {$syncRecords->count()} sync records to process.");

        $attached = 0;
        $skipped = 0;

        foreach ($syncRecords as $sync) {
            $product = Product::find($sync->product_id);
            if (! $product) {
                $skipped++;

                continue;
            }

            $exists = DB::table('shop_product')
                ->where('shop_id', $sync->shop_id)
                ->where('product_id', $product->id)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $platformData = $sync->platform_data ?? [];
            $sellingPrice = $platformData['price'] ?? $product->selling_price;
            // Only the locally recorded purchase cost is trustworthy here.
            // `regular_price`/`compare_at_price` are pre-discount SELLING prices,
            // and using them as a cost put products underwater on every sale.
            $costPrice = $product->hasPurchaseCost() ? $product->cost_price : null;
            $stockQty = $platformData['stock_quantity'] ?? $platformData['inventory_quantity'] ?? $product->stock_quantity;

            DB::table('shop_product')->insert([
                'shop_id' => $sync->shop_id,
                'product_id' => $product->id,
                'stock_quantity' => (int) $stockQty,
                'cost_price' => $costPrice ? (float) $costPrice : null,
                'selling_price' => $sellingPrice ? (float) $sellingPrice : null,
                'is_active' => $product->status->value === 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $attached++;
        }

        $this->info("Backfill complete: {$attached} attached, {$skipped} skipped.");

        return self::SUCCESS;
    }
}
