<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('saved_report_id')->nullable();
            $table->string('name');
            $table->string('type');
            $table->string('format');
            $table->string('status');
            $table->json('filters')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('row_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('saved_report_id')->references('id')->on('saved_reports')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
