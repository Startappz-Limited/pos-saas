<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            // Transaction details
            $table->string('transaction_number')->unique();
            $table->string('type'); // CreditTransactionType enum
            $table->string('reference_type')->nullable(); // Sale, Payment, Adjustment
            $table->unsignedBigInteger('reference_id')->nullable();

            // Amounts
            $table->decimal('debit', 15, 2)->default(0); // Increases balance (purchases)
            $table->decimal('credit', 15, 2)->default(0); // Decreases balance (payments)
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);

            // Due date for credit sales
            $table->date('due_date')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->integer('days_overdue')->default(0);

            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('uuid');
            $table->index('transaction_number');
            $table->index(['credit_account_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['due_date', 'is_overdue']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
