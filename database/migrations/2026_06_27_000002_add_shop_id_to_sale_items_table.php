<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the owning shop on each sale line so reports can attribute
     * product sales per shop, even when a single sale mixes products that
     * belong to different shops.
     */
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('product_id')
                ->constrained()
                ->nullOnDelete();

            $table->index('shop_id');
        });

        $this->backfillItemShop();
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_id');
        });
    }

    /**
     * Re-attribute existing sale lines from their product's owning shop,
     * falling back to the parent sale's shop when the product is missing.
     */
    private function backfillItemShop(): void
    {
        DB::table('sale_items')
            ->whereNull('shop_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.id', 'sale_items.product_id');
            })
            ->update([
                'shop_id' => DB::raw('(SELECT products.shop_id FROM products WHERE products.id = sale_items.product_id)'),
            ]);

        DB::table('sale_items')
            ->whereNull('shop_id')
            ->update([
                'shop_id' => DB::raw('(SELECT sales.shop_id FROM sales WHERE sales.id = sale_items.sale_id)'),
            ]);
    }
};
