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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Purchase Order Reference
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();

            // Product Information
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->nullOnDelete();

            // Order Details
            $table->string('sku')->nullable();
            $table->string('product_name');
            $table->json('variation_attributes')->nullable();

            // Quantities
            $table->decimal('quantity_ordered', 15, 2);
            $table->decimal('quantity_received', 15, 2)->default(0);
            $table->decimal('quantity_remaining', 15, 2);
            $table->string('unit')->default('pcs');

            // Pricing
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);

            // Additional Information
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            // Audit Fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // Indexes
            $table->index(['purchase_order_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
