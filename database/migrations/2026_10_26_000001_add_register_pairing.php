<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register pairing (Phase 7, decision 4.5): a till device is paired to one of
 * a shop's registers, and the tokens it signs in with are bound to that
 * register, so they act for its shop only. Re-pairing a register to another
 * device gives it a new code and revokes the old device's tokens.
 *
 * Re-pairing changes a register's code, so the shop's default register is
 * now marked by `is_default` rather than found by its code `R1`; every
 * register coded `R1` today is its shop's default and is marked so.
 *
 * Additive: existing tokens have no register and keep working as before.
 * `register_pairings` is the history of who paired which device, with which
 * code, and when it ended.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('code');
        });
        DB::table('registers')->where('code', 'R1')->update(['is_default' => true]);

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('register_id')->nullable()->after('business_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('register_pairings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->constrained()->cascadeOnDelete();
            $table->string('device_id');
            $table->string('device_name')->nullable();
            $table->string('code')->comment('The register code while this device held it');
            $table->foreignId('paired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paired_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason')->nullable()->comment('repaired: another device took the register');
            $table->timestamps();
            $table->index(['register_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_pairings');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('register_id');
        });

        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
