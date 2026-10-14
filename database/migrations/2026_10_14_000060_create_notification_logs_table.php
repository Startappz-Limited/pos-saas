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
            $table->uuid('uuid');
            $table->unsignedBigInteger('alert_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('channel');
            $table->string('type');
            $table->string('status');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('content')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('status');
            $table->index(['user_id', 'channel']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('alert_id')->references('id')->on('alerts')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
