<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FEFO, batches and expiry (stage 11, step 5): a business's type (never
 * required, restricts nothing — only what's created afterwards defaults
 * to); a batch's number and expiry, carried on the cost layer it becomes;
 * whether a variation requires both on every line that moves its stock;
 * the shop's own expiry-touch bookkeeping; and the adjustment-line fields
 * batches need (an increase's own cost and batch details, a decrease's
 * chosen batch).
 *
 * All new, nullable columns: nothing to carry across for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
        });

        Schema::table('cost_layers', function (Blueprint $table) {
            $table->string('batch_number')->nullable()->after('unit_cost');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->index(['shop_id', 'variation_id', 'expiry_date']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->boolean('track_batches')->default(false)->after('is_default');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->timestamp('expiry_touched_at')->nullable()->after('mpesa_verified_at');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            // An increase line's own batch and cost (never applied without one entered: an unknown cost
            // is kept as null, not the fallback). A decrease line's chosen batch, passed to consume() as
            // its preferred layer: a damaged carton from one delivery comes out of that delivery's batch.
            $table->unsignedBigInteger('cost_layer_id')->nullable()->after('quantity_change');
            $table->string('batch_number')->nullable()->after('cost_layer_id');
            $table->date('expiry_date')->nullable()->after('batch_number');
            $table->decimal('unit_cost', 10, 4)->nullable()->after('expiry_date');
            $table->foreign('cost_layer_id')->references('id')->on('cost_layers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('cost_layers', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'variation_id', 'expiry_date']);
            $table->dropColumn(['batch_number', 'expiry_date']);
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('track_batches');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('expiry_touched_at');
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['cost_layer_id']);
            $table->dropColumn(['cost_layer_id', 'batch_number', 'expiry_date', 'unit_cost']);
        });
    }
};
