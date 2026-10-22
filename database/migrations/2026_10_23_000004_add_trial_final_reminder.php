<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6: a trial is reminded twice, three days and one day before it ends;
 * `renewal_reminded_at` records the first, this the second.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('trial_final_reminded_at')->nullable()->after('renewal_reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('trial_final_reminded_at');
        });
    }
};
