<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjustment lines in any unit (stage 11, step 4): a line may name the unit it was entered in
 * (`unit_id`) and keep the signed quantity as entered (`unit_quantity`). `quantity_change` stays the
 * signed base quantity the stock moves by, so every reader of it is unchanged.
 *
 * Both columns are new and nullable: an existing line has no unit and nothing to carry across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('quantity_change')->constrained('product_units')->nullOnDelete();
            $table->decimal('unit_quantity', 15, 3)->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_quantity']);
        });
    }
};
