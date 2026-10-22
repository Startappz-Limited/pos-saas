<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: an owner may use the system unverified until this moment, then is
 * blocked until the email is verified. Set at signup and on an email change.
 * Business owners that are unverified when this runs get 24 hours from now;
 * staff are never asked to verify.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('verification_deadline_at')->nullable()->after('email_verified_at');
        });

        // Owners only: staff added by an admin were never asked to verify.
        DB::table('users')->whereNull('email_verified_at')
            ->whereIn('id', DB::table('businesses')->whereNotNull('primary_owner_id')->select('primary_owner_id'))
            ->update(['verification_deadline_at' => now()->addHours(24)]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_deadline_at');
        });
    }
};
