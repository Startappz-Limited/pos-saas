<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('sale_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('stock_intake_id')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->string('condition')->nullable();
            $table->text('condition_notes')->nullable();
            $table->boolean('is_restockable')->default(false);
            $table->boolean('is_restocked')->default(false);
            $table->boolean('return_to_supplier')->default(false);
            $table->timestamp('return_to_supplier_at')->nullable();
            $table->text('return_to_supplier_notes')->nullable();
            $table->timestamp('restocked_at')->nullable();
            $table->timestamps();
            $table->index('return_id');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->cascadeOnDelete();
            $table->foreign('stock_intake_id')->references('id')->on('stock_intakes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
