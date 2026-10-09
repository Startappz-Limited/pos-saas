<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('alert_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('channel'); // NotificationChannel enum
            $table->string('type');
            $table->string('status'); // pending, sent, failed, delivered

            $table->string('recipient'); // email address, phone number
            $table->string('subject')->nullable();
            $table->text('content')->nullable();

            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index(['user_id', 'channel']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
