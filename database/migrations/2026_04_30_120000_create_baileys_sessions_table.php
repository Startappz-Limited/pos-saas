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
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name')->default('Default');
            $table->string('session_key')->unique(); // identifier sent to Node bridge
            $table->string('jid')->nullable();        // own WhatsApp JID once connected
            $table->string('phone_number')->nullable();
            $table->string('display_name')->nullable();

            $table->string('status')->default('pending');
            $table->text('qr_code')->nullable();      // last QR (data URL or raw string)
            $table->timestamp('qr_expires_at')->nullable();

            $table->json('device_info')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index('jid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_sessions');
    }
};
