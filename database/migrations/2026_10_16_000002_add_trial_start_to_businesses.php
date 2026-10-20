<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service signup (stage 3): a business created at signup waits for its
 * owner's email to be verified (status `pending_verification`), and its trial
 * starts at that moment, so a throwaway signup never opens a trial. Phase 4's
 * subscriptions read the start from here.
 *
 * Additive: existing businesses keep their status and have no trial start.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->timestamp('trial_started_at')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('trial_started_at');
        });
    }
};
