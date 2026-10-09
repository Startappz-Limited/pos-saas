<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Payment details
            $table->string('payment_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('status');

            // Method-specific details
            $table->string('reference_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('card_last_four')->nullable();
            $table->string('mobile_provider')->nullable();
            $table->string('mobile_number')->nullable();

            // Balance tracking
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);

            // Timestamps
            $table->timestamp('payment_date');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->text('notes')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('payment_number');
            $table->index(['sale_id', 'status']);
            $table->index(['shop_id', 'payment_date']);
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
