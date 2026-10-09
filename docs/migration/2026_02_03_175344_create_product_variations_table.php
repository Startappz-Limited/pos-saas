<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name'); // e.g., "Small Red", "Large Blue"
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->json('attributes'); // e.g., {"size": "Small", "color": "Red"}
            $table->decimal('cost_price', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->integer('stock_quantity')->default(0);
            $table->string('image')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
