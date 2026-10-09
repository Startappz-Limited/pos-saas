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
            $table->foreignId('baileys_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('jid');
            $table->string('name')->nullable();
            $table->string('type')->default('private'); // private|group|channel|broadcast|status

            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();

            $table->boolean('is_archived')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_muted')->default(false);

            $table->json('metadata')->nullable(); // group description, participants, etc.

            $table->timestamps();

            $table->unique(['baileys_session_id', 'jid']);
            $table->index(['shop_id', 'type']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_chats');
    }
};
