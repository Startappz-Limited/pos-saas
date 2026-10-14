<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('cost_layer_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->integer('quantity_allocated');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index('cost_layer_id');
            $table->index('sale_item_id');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('cost_layer_id')->references('id')->on('cost_layers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_allocations');
    }
};
