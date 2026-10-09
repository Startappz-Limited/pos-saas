<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_recipients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->timestamp('dismissed_at')->nullable();

            $table->timestamps();

            $table->unique(['alert_id', 'user_id']);
            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_recipients');
    }
};
