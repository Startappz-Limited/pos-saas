<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock adjustments move the variation they name (stage 10, step 1g).
 *
 * - `stock_adjustment_items.variation_id`: existing lines get their product's
 *   default variation, which is what the code moved (counts printed);
 * - `quantity_before` and `quantity_after` become nullable: they are written
 *   when stock moves, not when a draft line is added;
 * - `quantity_change` becomes signed by the line: a decrease adjustment's lines
 *   saved by the old form held a positive number (the type gave the direction),
 *   so they are negated; imported ones were already negative and are left alone;
 * - adjustment numbers become a gapless series per shop, so the unique index on
 *   `adjustment_number` becomes `(shop_id, adjustment_number)`; existing numbers
 *   are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('variation_id')->nullable()->after('product_id')->constrained('product_variations')->nullOnDelete();
            $table->integer('quantity_before')->nullable()->change();
            $table->integer('quantity_after')->nullable()->change();
        });

        $before = DB::table('stock_adjustment_items')->whereNull('variation_id')->count();
        DB::table('stock_adjustment_items')->whereNull('variation_id')->update([
            'variation_id' => DB::raw('(select pv.id from product_variations pv where pv.product_id = stock_adjustment_items.product_id and pv.is_default = 1 order by pv.id limit 1)'),
        ]);
        $after = DB::table('stock_adjustment_items')->whereNull('variation_id')->count();

        $negated = DB::table('stock_adjustment_items')->where('quantity_change', '>', 0)
            ->whereIn('stock_adjustment_id', DB::table('stock_adjustments')->where('type', 'decrease')->select('id'))
            ->update(['quantity_change' => DB::raw('-quantity_change')]);

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropUnique(['adjustment_number']);
            $table->unique(['shop_id', 'adjustment_number']);
        });

        if (! app()->runningUnitTests()) {
            echo '  Adjustment lines given their default variation: '.($before - $after)." of {$before}; {$after} left without one.\n";
            echo "  Decrease lines made negative: {$negated}.\n";
        }
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'adjustment_number']);
            $table->unique('adjustment_number');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
        });
    }
};
