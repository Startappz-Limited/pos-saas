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
            $table->uuid('uuid')->unique();

            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_intake_id')->nullable()->constrained('stock_intakes');

            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);

            $table->string('condition')->nullable(); // new, opened, damaged, defective
            $table->text('condition_notes')->nullable();
            $table->boolean('is_restockable')->default(false);
            $table->boolean('is_restocked')->default(false);
            $table->timestamp('restocked_at')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index('return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};
