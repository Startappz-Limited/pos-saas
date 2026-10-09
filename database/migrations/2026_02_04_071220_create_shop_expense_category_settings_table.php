<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_expense_category_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();

            // Shop-specific overrides
            $table->boolean('is_enabled')->default(true);
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->nullable();
            $table->decimal('approval_threshold', 15, 2)->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->unique(['shop_id', 'expense_category_id'], 'shop_expense_category_unique');
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_expense_category_settings');
    }
};
