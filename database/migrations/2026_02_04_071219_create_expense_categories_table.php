<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Hierarchy
            $table->foreignId('parent_id')->nullable()->constrained('expense_categories')->nullOnDelete();
            $table->integer('depth')->default(0);
            $table->string('path')->nullable(); // Materialized path for fast hierarchy queries

            // Basic info
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code')->unique(); // Short code like EXP-OPS-001
            $table->text('description')->nullable();

            // Classification
            $table->string('type'); // ExpenseCategoryType enum
            $table->boolean('is_operational')->default(true);
            $table->boolean('is_tax_deductible')->default(false);

            // Budget settings
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold', 15, 2)->nullable(); // Require approval above this amount

            // Display
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);

            // Status
            $table->string('status'); // CommonStatus enum

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('slug');
            $table->index('code');
            $table->index('parent_id');
            $table->index('type');
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
