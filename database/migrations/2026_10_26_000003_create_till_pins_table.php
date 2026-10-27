<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Till PINs (Phase 7): one per person per business, for signing in on a
 * till while it is offline. Only the hash is kept; the till receives it with
 * the staff list and checks a PIN offline. Setting and resetting a PIN are
 * audited. New table; no existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('till_pins', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pin_hash');
            $table->timestamp('set_at');
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('till_pins');
    }
};
