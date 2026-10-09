<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('suppliers')->nullOnDelete();

            // Expense details
            $table->string('expense_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('KES');

            // Payment details
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->date('paid_date')->nullable();

            // Dates
            $table->date('expense_date');
            $table->date('due_date')->nullable();

            // Status & Approval
            $table->string('status'); // ExpenseStatus enum
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Recurring
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable(); // RecurrenceFrequency enum
            $table->date('recurrence_end_date')->nullable();
            $table->foreignId('parent_expense_id')->nullable()->constrained('expenses')->nullOnDelete();

            // Tax
            $table->boolean('is_tax_deductible')->default(false);
            $table->decimal('tax_amount', 15, 2)->nullable();

            $table->text('notes')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('expense_number');
            $table->index(['shop_id', 'expense_date']);
            $table->index(['category_id', 'status']);
            $table->index(['status', 'expense_date']);
            $table->index('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
