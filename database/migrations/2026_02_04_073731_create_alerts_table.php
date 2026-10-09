<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();

            // Alert details
            $table->string('title');
            $table->text('message');
            $table->string('type'); // AlertType enum
            $table->string('severity'); // AlertSeverity enum
            $table->string('category'); // AlertCategory enum

            // Reference
            $table->nullableMorphs('alertable'); // Product, Sale, Expense, etc.

            // Status
            $table->boolean('is_read')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();

            // Scheduling
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            // Metadata
            $table->json('data')->nullable();
            $table->json('actions')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('uuid');
            $table->index(['shop_id', 'type', 'is_resolved']);
            $table->index(['type', 'severity']);
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
