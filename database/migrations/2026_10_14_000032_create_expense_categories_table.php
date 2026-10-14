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
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('depth')->default(0);
            $table->string('path')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('type');
            $table->boolean('is_operational')->default(true);
            $table->boolean('is_tax_deductible')->default(false);
            $table->decimal('monthly_budget', 15, 2)->nullable();
            $table->decimal('yearly_budget', 15, 2)->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold', 15, 2)->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('alive')->nullable()->virtualAs('case when deleted_at is null then 1 end')->comment('1 until soft-deleted, then NULL: per-business unique keys skip deleted rows');
            $table->index('code');
            $table->unique(['business_id', 'code', 'alive']);
            $table->index('parent_id');
            $table->index('slug');
            $table->unique(['business_id', 'slug', 'alive']);
            $table->index(['status', 'sort_order']);
            $table->index('type');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('expense_categories')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
