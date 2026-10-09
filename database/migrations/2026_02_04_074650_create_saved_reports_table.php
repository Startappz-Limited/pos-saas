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
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // ReportType enum

            // Filters & Parameters
            $table->json('filters')->nullable();
            $table->json('columns')->nullable();
            $table->string('group_by')->nullable();
            $table->string('sort_by')->nullable();
            $table->string('sort_direction')->default('desc');

            // Scheduling
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_frequency')->nullable();
            $table->json('schedule_recipients')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();

            $table->boolean('is_public')->default(false);

            $table->timestamps();

            $table->index('uuid');
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
