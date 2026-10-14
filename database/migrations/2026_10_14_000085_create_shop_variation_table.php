<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-shop stock, reorder level and price and cost overrides for every
 * sellable item (decision 4.2): every product has at least one variation (a
 * default one for a simple product), so one row per shop and variation covers
 * everything. It becomes the only stock source in stage 1b, which moves the
 * writers and readers off products/product_variations.stock_quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_variation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('product_variations')->cascadeOnDelete();
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->nullable()->comment('NULL: the product\'s reorder level');
            $table->decimal('cost_price', 10, 2)->nullable()->comment('NULL: the variation\'s cost');
            $table->decimal('selling_price', 10, 2)->nullable()->comment('NULL: the variation\'s price');
            $table->decimal('wholesale_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['shop_id', 'variation_id']);
            $table->index(['product_id', 'shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_variation');
    }
};
