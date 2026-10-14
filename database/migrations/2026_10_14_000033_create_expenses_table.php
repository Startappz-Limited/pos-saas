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
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('expense_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('KES');
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->boolean('settled_from_register')->default(false);
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->date('paid_date')->nullable();
            $table->date('expense_date');
            $table->date('due_date')->nullable();
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_frequency')->nullable();
            $table->date('recurrence_end_date')->nullable();
            $table->unsignedBigInteger('parent_expense_id')->nullable();
            $table->boolean('is_tax_deductible')->default(false);
            $table->decimal('tax_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['category_id', 'status']);
            $table->index('expense_number');
            $table->unique('expense_number');
            $table->index('is_recurring');
            $table->index(['shop_id', 'expense_date']);
            $table->index(['status', 'expense_date']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cash_register_id')->references('id')->on('cash_registers')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('expense_categories')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_expense_id')->references('id')->on('expenses')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('vendor_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
