<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gapless per-shop invoice counters.
 *
 * One row per (shop, series); `series` carries the reset period, e.g. "2026" for
 * a yearly sequence or "*" for a continuous one. The unique key is what makes
 * concurrent tills safe: two registers racing to create the same row collide on
 * the constraint instead of both claiming number 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_sequences')) {
            return;
        }

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('series', 32);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['shop_id', 'series']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
