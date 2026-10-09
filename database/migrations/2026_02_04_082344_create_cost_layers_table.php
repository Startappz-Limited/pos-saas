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
            $table->uuid('uuid')->unique();

            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_intake_id')->constrained()->cascadeOnDelete();

            $table->decimal('unit_cost', 15, 2);
            $table->integer('original_quantity');
            $table->integer('remaining_quantity');

            $table->date('received_date');
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['product_id', 'shop_id', 'remaining_quantity']);
            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_layers');
    }
};
