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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->uuid('stock_batch_id')->nullable();

            // Movement details
            $table->string('movement_type'); // Uses StockMovementType enum
            $table->integer('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->decimal('unit_cost', 12, 2)->nullable();

            // Reference
            $table->string('reference_type')->nullable(); // 'sale', 'adjustment', 'transfer', etc.
            $table->string('reference_uuid')->nullable();
            $table->text('notes')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Note: stock_batch_id foreign key will be added when stock_batches table is created
            $table->index(['product_id', 'variation_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
