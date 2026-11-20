<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selling in units (stage 11, step 3): a sale or return line records the unit
 * it was in and the base quantity that moves the ledger. `unit_id` is
 * nullable — a line with no unit is the base unit (`StockLedger` and the
 * totals engine already treat a null unit that way) — and never cascades: a
 * unit only soft-deletes, so there is nothing for it to cascade from.
 *
 * Both columns are new; nothing has written them before this stage, so there
 * is nothing to carry across. `base_quantity` is backfilled from `quantity`
 * (the only sensible value for every existing row, which all predate units)
 * so the column can be NOT NULL from the start.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('variation_id')->constrained('product_units')->nullOnDelete();
            $table->decimal('base_quantity', 15, 3)->nullable()->after('quantity');
        });
        DB::table('sale_items')->update(['base_quantity' => DB::raw('quantity')]);
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('base_quantity', 15, 3)->nullable(false)->change();
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('product_id')->constrained('product_units')->nullOnDelete();
            $table->decimal('base_quantity', 15, 3)->nullable()->after('quantity');
        });
        DB::table('return_items')->update(['base_quantity' => DB::raw('quantity')]);
        Schema::table('return_items', function (Blueprint $table) {
            $table->decimal('base_quantity', 15, 3)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('base_quantity');
        });

        Schema::table('return_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn('base_quantity');
        });
    }
};
