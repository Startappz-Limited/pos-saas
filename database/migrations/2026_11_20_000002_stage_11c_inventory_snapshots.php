<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 11c, step 3: inventory snapshots per shop, variation and day, of a kind (daily, month_end, manual) and from
 * a source (on_time, rebuilt, late). Every snapshot names its variation, so the unique key has no NULL that MySQL
 * would let repeat. Additive: an existing snapshot gets its product's default variation and is a manual one, as the
 * stage decides (none exists on UAT). `shops.snapshot_taken_at` records the shop's last nightly run.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_snapshots')->whereNull('variation_id')->update([
            'variation_id' => DB::raw('(select product_variations.id from product_variations where product_variations.product_id = inventory_snapshots.product_id and product_variations.is_default = 1 limit 1)'),
        ]);

        Schema::table('inventory_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['variation_id']);
            $table->dropUnique('inventory_snapshot_unique');
            $table->unsignedBigInteger('variation_id')->nullable(false)->change();
            $table->decimal('unknown_cost_quantity', 15, 3)->default(0)->after('total_value');
            $table->decimal('shortfall_quantity', 15, 3)->default(0)->after('unknown_cost_quantity');
            $table->string('kind', 20)->default('manual')->after('snapshot_date');
            $table->string('source', 20)->default('on_time')->after('kind');
            $table->unique(['shop_id', 'variation_id', 'snapshot_date', 'kind'], 'inventory_snapshot_day_kind_unique');
            // Restrict, not null: a variation with history can't be deleted without losing it.
            $table->foreign('variation_id')->references('id')->on('product_variations')->restrictOnDelete();
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->timestamp('snapshot_taken_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('snapshot_taken_at');
        });

        Schema::table('inventory_snapshots', function (Blueprint $table): void {
            $table->dropForeign(['variation_id']);
            $table->dropUnique('inventory_snapshot_day_kind_unique');
            $table->dropColumn(['unknown_cost_quantity', 'shortfall_quantity', 'kind', 'source']);
            $table->unsignedBigInteger('variation_id')->nullable()->change();
            $table->unique(['shop_id', 'product_id', 'variation_id', 'snapshot_date'], 'inventory_snapshot_unique');
            $table->foreign('variation_id')->references('id')->on('product_variations')->nullOnDelete();
        });
    }
};
