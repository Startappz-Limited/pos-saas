<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_item_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('sale_item_id');
            $table->uuid('stock_batch_id')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('total_cost', 12, 2);
            $table->timestamps();
            $table->index('stock_batch_id');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_item_stock_allocations');
    }
};
