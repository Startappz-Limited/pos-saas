<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('image')->nullable();
            $table->integer('order')->default(0);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('alive')->nullable()->virtualAs('case when deleted_at is null then 1 end')->comment('1 until soft-deleted, then NULL: per-business unique keys skip deleted rows');
            $table->index('order');
            $table->index('parent_id');
            $table->unique(['business_id', 'slug', 'alive']);
            $table->index(['status', 'parent_id']);
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
