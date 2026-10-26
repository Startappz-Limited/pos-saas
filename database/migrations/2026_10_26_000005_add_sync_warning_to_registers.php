<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a till's owners were last told it hasn't synced (Phase 7, decision
 * 4.5), so they are told once per silence; its next sync clears it.
 * Additive: a nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->timestamp('sync_overdue_warned_at')->nullable()->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn('sync_overdue_warned_at');
        });
    }
};
