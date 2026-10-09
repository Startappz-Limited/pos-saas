<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shop_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->decimal('cost_price', 10, 2)->nullable()->comment('Shop-specific cost override');
            $table->decimal('selling_price', 10, 2)->nullable()->comment('Shop-specific price override');
            $table->decimal('wholesale_price', 10, 2)->nullable()->comment('Shop-specific wholesale override');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'product_id']);
            $table->index(['shop_id', 'is_active']);
            $table->index(['product_id', 'stock_quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_product');
    }
};
