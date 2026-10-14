<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('stock_intake_id');
            $table->decimal('unit_cost', 15, 2);
            $table->integer('original_quantity');
            $table->integer('remaining_quantity');
            $table->date('received_date');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'shop_id', 'remaining_quantity']);
            $table->index('received_date');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('stock_intake_id')->references('id')->on('stock_intakes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_layers');
    }
};
