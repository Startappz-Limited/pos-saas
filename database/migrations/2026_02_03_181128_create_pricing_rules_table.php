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
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // PricingType enum

            // Product relationship
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Pricing details
            $table->decimal('price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();

            // Bulk pricing
            $table->integer('min_quantity')->nullable();
            $table->integer('max_quantity')->nullable();

            // Customer specific
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();

            // Time-based / Promotional
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            // Priority (higher number = higher priority)
            $table->integer('priority')->default(0);

            // Status
            $table->boolean('is_active')->default(true);

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['product_id', 'type', 'is_active']);
            $table->index(['customer_id', 'is_active']);
            $table->index(['start_date', 'end_date']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
