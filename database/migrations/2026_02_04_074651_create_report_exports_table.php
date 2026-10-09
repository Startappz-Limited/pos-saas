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
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('saved_report_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('type'); // ReportType enum
            $table->string('format'); // ExportFormat enum
            $table->string('status'); // pending, processing, completed, failed

            $table->json('filters')->nullable();

            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->integer('row_count')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
