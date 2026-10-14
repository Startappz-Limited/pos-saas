<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('group_by')->nullable();
            $table->string('sort_by')->nullable();
            $table->string('sort_direction')->default('desc');
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency')->nullable();
            $table->json('schedule_recipients')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'type']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
