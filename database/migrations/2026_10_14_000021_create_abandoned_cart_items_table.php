<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('abandoned_cart_id');
            $table->string('platform_product_id')->nullable();
            $table->string('platform_variation_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variation_id')->nullable();
            $table->string('sku')->nullable();
            $table->string('name');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('line_total', 10, 2)->default(0);
            $table->string('image_url', 2048)->nullable();
            $table->timestamps();
            $table->index('product_id');
            $table->foreign('abandoned_cart_id')->references('id')->on('abandoned_carts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('variation_id')->references('id')->on('product_variations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_cart_items');
    }
};
