<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            // Credit limits
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);

            // Status
            $table->string('status'); // CreditAccountStatus enum
            $table->boolean('is_suspended')->default(false);
            $table->string('suspension_reason')->nullable();

            // Payment terms
            $table->integer('payment_terms_days')->default(30);
            $table->integer('grace_period_days')->default(7);

            // Timestamps
            $table->timestamp('limit_updated_at')->nullable();
            $table->foreignId('limit_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_purchase_at')->nullable();
            $table->timestamp('last_payment_at')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->unique(['customer_id', 'shop_id']);
            $table->index(['shop_id', 'status']);
            $table->index('current_balance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_accounts');
    }
};
