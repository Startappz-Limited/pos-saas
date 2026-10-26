<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sync push (Phase 7, decision 4.5).
 *
 * - `sync_push_receipts`: one row per item a till pushed and the server
 *   applied, unique per business and `client_uuid`, so a batch pushed twice
 *   is applied once and the second push gets the first result back.
 * - `till_pins.locked_at`: a person locked out on a till until someone who may
 *   unlock them does (after repeated lockouts, decided 26 October 2026); kept
 *   on the server so the business's other tills lock them too.
 *
 * Additive: a new table, and nullable columns on a table with no rows yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_push_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('client_uuid');
            $table->string('type', 40);
            $table->json('result')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();
            $table->unique(['business_id', 'client_uuid']);
        });

        Schema::table('till_pins', function (Blueprint $table) {
            $table->timestamp('locked_at')->nullable()->after('set_at');
            $table->foreignId('locked_on_register_id')->nullable()->after('locked_at')->constrained('registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('till_pins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('locked_on_register_id');
            $table->dropColumn('locked_at');
        });

        Schema::dropIfExists('sync_push_receipts');
    }
};
