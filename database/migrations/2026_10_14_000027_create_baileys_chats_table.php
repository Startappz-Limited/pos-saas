<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baileys_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('baileys_session_id');
            $table->unsignedBigInteger('shop_id');
            $table->string('jid');
            $table->string('phone', 32)->nullable();
            $table->string('name')->nullable();
            $table->string('type')->default('private');
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_muted')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['baileys_session_id', 'jid']);
            $table->index('last_message_at');
            $table->index(['shop_id', 'phone']);
            $table->index(['shop_id', 'type']);
            $table->foreign('baileys_session_id')->references('id')->on('baileys_sessions')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_chats');
    }
};
