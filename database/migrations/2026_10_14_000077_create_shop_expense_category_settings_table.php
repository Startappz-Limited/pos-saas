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
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('expense_category_id');
            $table->boolean('is_enabled')->default(true);
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->nullable();
            $table->decimal('approval_threshold', 15, 2)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('uuid');
            $table->unique('uuid');
            $table->unique(['shop_id', 'expense_category_id'], 'shop_expense_category_unique');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_expense_category_settings');
    }
};
