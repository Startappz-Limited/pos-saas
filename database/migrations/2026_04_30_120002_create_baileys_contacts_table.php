<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baileys_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baileys_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('jid');
            $table->string('phone')->nullable();
            $table->string('name')->nullable();        // saved name
            $table->string('push_name')->nullable();   // their public WA name
            $table->string('profile_picture_url')->nullable();
            $table->boolean('is_business')->default(false);
            $table->boolean('is_blocked')->default(false);

            $table->timestamps();

            $table->unique(['baileys_session_id', 'jid']);
            $table->index(['shop_id', 'phone']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_contacts');
    }
};
