<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->string('platform_product_id')->nullable();
            $table->string('platform_line_item_id')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->json('platform_data')->nullable();
            $table->timestamps();

            $table->index(['ecommerce_order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_items');
    }
};
