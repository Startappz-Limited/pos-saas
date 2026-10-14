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
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->json('attributes')->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->decimal('wholesale_price', 10, 2)->nullable()->default(0);
            $table->string('tax_class', 32)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('image')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_default')->default(false)->comment('The variation a simple product sells as');
            $table->boolean('default_marker')->nullable()->virtualAs('case when is_default = 1 then 1 end')->comment('1 on the default variation, NULL otherwise: one default per product');
            $table->timestamps();
            $table->index('barcode');
            $table->unique(['business_id', 'barcode']);
            $table->index(['product_id', 'status']);
            $table->index('sku');
            $table->unique(['business_id', 'sku']);
            $table->unique('uuid');
            $table->unique(['product_id', 'default_marker']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};
