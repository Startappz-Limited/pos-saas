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
            $table->uuid('uuid');
            $table->unsignedBigInteger('baileys_session_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('baileys_chat_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('chat_jid');
            $table->string('sender_jid')->nullable();
            $table->string('wa_message_id')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('type')->default('text');
            $table->string('status')->default('pending');
            $table->longText('content')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('media_filename')->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['baileys_chat_id', 'created_at']);
            $table->index(['shop_id', 'created_at'], 'baileys_messages_shop_created_index');
            $table->index(['shop_id', 'baileys_session_id']);
            $table->index(['shop_id', 'customer_id']);
            $table->index(['shop_id', 'status']);
            $table->unique('uuid');
            $table->index('wa_message_id');
            $table->foreign('baileys_chat_id')->references('id')->on('baileys_chats')->nullOnDelete();
            $table->foreign('baileys_session_id')->references('id')->on('baileys_sessions')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_messages');
    }
};
