<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baileys_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('name')->default('Default');
            $table->string('session_key');
            $table->string('jid')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('display_name')->nullable();
            $table->string('status')->default('pending');
            $table->text('qr_code')->nullable();
            $table->timestamp('qr_expires_at')->nullable();
            $table->json('device_info')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('jid');
            $table->unique('session_key');
            $table->index(['shop_id', 'status']);
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_sessions');
    }
};
