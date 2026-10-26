<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline trading leases (Phase 7, decision 4.5): each lease a till was given,
 * so a pushed sale is accepted only if its recorded time falls inside one the
 * server issued. A device clock set forward can't make a lease that doesn't
 * exist. New table; no existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_leases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('trade_until');
            $table->timestamps();
            $table->index(['register_id', 'issued_at', 'trade_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_leases');
    }
};
