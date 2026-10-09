<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baileys_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('baileys_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('baileys_chat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('chat_jid');                  // chat the message lives in
            $table->string('sender_jid')->nullable();    // actual sender (matters in groups)
            $table->string('wa_message_id')->nullable(); // ID returned by Baileys / WhatsApp

            $table->string('direction')->default('outbound');
            $table->string('type')->default('text');
            $table->string('status')->default('pending');

            $table->longText('content')->nullable();     // text body or caption
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_filename')->nullable();
            $table->json('payload')->nullable();         // raw bridge payload for debugging / advanced types

            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->timestamps();

            $table->index(['shop_id', 'baileys_session_id']);
            $table->index(['shop_id', 'customer_id']);
            $table->index(['baileys_chat_id', 'created_at']);
            $table->index(['shop_id', 'status']);
            $table->index('wa_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_messages');
    }
};
