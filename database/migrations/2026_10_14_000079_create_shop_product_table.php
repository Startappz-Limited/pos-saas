<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_product', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('stock_quantity')->default(0);
            $table->integer('reorder_level')->default(10);
            $table->decimal('cost_price', 10, 2)->nullable()->comment('Shop-specific cost override');
            $table->decimal('selling_price', 10, 2)->nullable()->comment('Shop-specific price override');
            $table->decimal('wholesale_price', 10, 2)->nullable()->comment('Shop-specific wholesale override');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['product_id', 'stock_quantity']);
            $table->index(['shop_id', 'is_active']);
            $table->unique(['shop_id', 'product_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product');
    }
};
