<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate sync records, keeping the one with the highest id
        $duplicates = DB::table('product_ecommerce_sync')
            ->select('shop_id', 'platform', 'platform_product_id', DB::raw('MAX(id) as keep_id'))
            ->groupBy('shop_id', 'platform', 'platform_product_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dupe) {
            DB::table('product_ecommerce_sync')
                ->where('shop_id', $dupe->shop_id)
                ->where('platform', $dupe->platform)
                ->where('platform_product_id', $dupe->platform_product_id)
                ->where('id', '!=', $dupe->keep_id)
                ->delete();
        }

        Schema::table('product_ecommerce_sync', function (Blueprint $table) {
            $table->dropIndex(['platform', 'platform_product_id']);
            $table->unique(['shop_id', 'platform', 'platform_product_id'], 'product_ecommerce_sync_shop_platform_product_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_ecommerce_sync', function (Blueprint $table) {
            $table->dropUnique('product_ecommerce_sync_shop_platform_product_unique');
            $table->index(['platform', 'platform_product_id']);
        });
    }
};
