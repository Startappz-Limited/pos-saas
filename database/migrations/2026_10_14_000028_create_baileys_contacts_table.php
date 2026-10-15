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
            $table->unsignedBigInteger('baileys_session_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('jid');
            $table->string('phone')->nullable();
            $table->string('name')->nullable();
            $table->string('push_name')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->boolean('is_business')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->unique(['baileys_session_id', 'jid']);
            $table->index('customer_id');
            $table->index(['shop_id', 'phone']);
            $table->foreign('baileys_session_id')->references('id')->on('baileys_sessions')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baileys_contacts');
    }
};
