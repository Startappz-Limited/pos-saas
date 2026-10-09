<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the owning shop to products and backfill existing rows.
     *
     * Each product belongs to exactly one shop. A sale line's shop is then
     * derived from its product, so sales can be attributed per shop in reports.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('shop_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained()
                ->nullOnDelete();

            $table->index('shop_id');
        });

        $this->backfillOwningShop();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_id');
        });
    }

    /**
     * Assign every existing product an owning shop.
     *
     * Priority: an existing shop_product pivot row, then an e-commerce sync
     * record imported from a platform, otherwise the first shop as a default
     * the user can re-assign from the product edit screen.
     */
    private function backfillOwningShop(): void
    {
        $defaultShopId = DB::table('shops')->orderBy('id')->value('id');

        if ($defaultShopId === null) {
            return;
        }

        // Derive from the shop_product pivot where a mapping already exists.
        if (Schema::hasTable('shop_product')) {
            DB::table('products')
                ->whereNull('shop_id')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('shop_product')
                        ->whereColumn('shop_product.product_id', 'products.id');
                })
                ->update([
                    'shop_id' => DB::raw('(SELECT MIN(shop_id) FROM shop_product WHERE shop_product.product_id = products.id)'),
                ]);
        }

        // Derive from e-commerce sync records imported from a platform.
        if (Schema::hasTable('product_ecommerce_syncs')) {
            DB::table('products')
                ->whereNull('shop_id')
                ->whereExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('product_ecommerce_syncs')
                        ->whereColumn('product_ecommerce_syncs.product_id', 'products.id');
                })
                ->update([
                    'shop_id' => DB::raw('(SELECT MIN(shop_id) FROM product_ecommerce_syncs WHERE product_ecommerce_syncs.product_id = products.id)'),
                ]);
        }

        // Everything still unassigned defaults to the first shop.
        DB::table('products')
            ->whereNull('shop_id')
            ->update(['shop_id' => $defaultShopId]);
    }
};
