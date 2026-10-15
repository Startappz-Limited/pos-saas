<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Per-shop stock becomes the only stock source (stage 1b, decision 4.2).
 *
 * Carries every legacy figure across, then drops the legacy columns:
 * - a product's stock (products.stock_quantity, through its default
 *   variation) and its variations' (product_variations.stock_quantity) go to
 *   the product's own shop: products.shop_id, else its first listed shop, else
 *   its business's oldest shop (the rule the legacy import uses);
 * - each figure gets an opening FIFO layer at the variation's cost (a default
 *   variation's is its product's); a negative figure becomes a provisional
 *   shortfall the next delivery settles;
 * - the totals are checked before anything is dropped.
 *
 * shop_product.stock_quantity was written only by the e-commerce sync (an
 * online store's figure, never the POS stock), so it isn't carried; the store
 * figure now lands in shop_variation when a sync runs.
 *
 * Take a snapshot first (php artisan db:snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $legacy = (int) DB::table('products')->sum('stock_quantity')
                + (int) DB::table('product_variations')->where('is_default', false)->sum('stock_quantity');
            $before = (int) DB::table('shop_variation')->sum('stock_quantity');

            DB::table('products')->orderBy('id')->each(function (object $product): void {
                $shopId = $product->shop_id
                    ?? DB::table('shop_product')->where('product_id', $product->id)->orderBy('shop_id')->value('shop_id')
                    ?? DB::table('shops')->where('business_id', $product->business_id)->orderBy('id')->value('id');
                $default = $this->defaultVariation($product);

                foreach (DB::table('product_variations')->where('product_id', $product->id)->get() as $variation) {
                    $quantity = (int) ($variation->id === $default->id ? $product->stock_quantity : $variation->stock_quantity);

                    if ($quantity === 0) {
                        continue;
                    }
                    if ($shopId === null) {
                        throw new RuntimeException("Product {$product->id} holds stock but its business has no shop to hold it.");
                    }

                    $this->place((int) $shopId, $product, $variation, $quantity);
                }
            });

            $moved = (int) DB::table('shop_variation')->sum('stock_quantity') - $before;
            if ($moved !== $legacy) {
                throw new RuntimeException("Stock move failed its check: {$legacy} units in the legacy columns, {$moved} moved. Nothing was changed.");
            }
        });

        // On MySQL that index also serves the product_id foreign key, which
        // needs an index of its own before it can go.
        Schema::table('shop_product', fn (Blueprint $table) => $table->index('product_id'));
        Schema::table('shop_product', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'stock_quantity']);
        });
        Schema::table('shop_product', fn (Blueprint $table) => $table->dropColumn('stock_quantity'));
        Schema::table('product_variations', fn (Blueprint $table) => $table->dropColumn('stock_quantity'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('stock_quantity'));
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->integer('stock_quantity')->default(0));
        Schema::table('product_variations', fn (Blueprint $table) => $table->integer('stock_quantity')->default(0));
        Schema::table('shop_product', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0);
            $table->index(['product_id', 'stock_quantity']);
        });
        Schema::table('shop_product', fn (Blueprint $table) => $table->dropIndex(['product_id']));

        // The figures come back as totals across shops; per-shop rows and
        // layers stay.
        foreach (DB::table('shop_variation')->get() as $row) {
            $variation = DB::table('product_variations')->where('id', $row->variation_id)->first();
            if ($variation === null) {
                continue;
            }
            if ($variation->is_default) {
                DB::table('products')->where('id', $row->product_id)->increment('stock_quantity', (int) $row->stock_quantity);
                DB::table('shop_product')->where('shop_id', $row->shop_id)->where('product_id', $row->product_id)->update(['stock_quantity' => (int) $row->stock_quantity]);
            } else {
                DB::table('product_variations')->where('id', $row->variation_id)->increment('stock_quantity', (int) $row->stock_quantity);
            }
        }
    }

    /**
     * The product's default variation, created if a row predates them.
     */
    private function defaultVariation(object $product): object
    {
        $default = DB::table('product_variations')->where('product_id', $product->id)->where('is_default', true)->first();
        if ($default !== null) {
            return $default;
        }

        DB::table('product_variations')->insert([
            'business_id' => $product->business_id,
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'sku' => 'DEFAULT-'.$product->uuid,
            'cost_price' => $product->cost_price,
            'selling_price' => $product->selling_price ?? 0,
            'wholesale_price' => $product->wholesale_price,
            'tax_class' => $product->tax_class,
            'status' => $product->status ?? 'active',
            'stock_quantity' => 0,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('product_variations')->where('product_id', $product->id)->where('is_default', true)->first();
    }

    /**
     * Adds the figure to the shop's row, with its opening FIFO cost.
     */
    private function place(int $shopId, object $product, object $variation, int $quantity): void
    {
        $now = now();
        DB::table('shop_variation')->insertOrIgnore([
            'shop_id' => $shopId, 'product_id' => $product->id, 'variation_id' => $variation->id,
            'stock_quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('shop_variation')->where('shop_id', $shopId)->where('variation_id', $variation->id)
            ->update(['stock_quantity' => DB::raw('stock_quantity + ('.$quantity.')'), 'updated_at' => $now]);

        $cost = $variation->cost_price ?? ($variation->is_default ? $product->cost_price : null);
        $common = [
            'uuid' => (string) Str::uuid(),
            'business_id' => $product->business_id,
            'shop_id' => $shopId,
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if ($quantity > 0) {
            DB::table('cost_layers')->insert([...$common,
                'source_type' => 'opening',
                'unit_cost' => $cost,
                'original_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'received_at' => $now,
            ]);

            return;
        }

        DB::table('cost_allocations')->insert([...$common,
            'consumer_type' => 'opening',
            'consumer_id' => $variation->id,
            'quantity_allocated' => -$quantity,
            'unit_cost' => $cost,
            'total_cost' => $cost === null ? null : round(-$quantity * (float) $cost, 2),
            'is_provisional' => true,
            'provisional_unit_cost' => $cost,
            'allocated_at' => $now,
        ]);
    }
};
