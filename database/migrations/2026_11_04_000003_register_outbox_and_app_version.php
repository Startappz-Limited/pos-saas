<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a till holds and runs (stage 10, step 5c): the unsynced items it last
 * reported (`outbox_count`, sent with every push and pull), so re-pairing it
 * can't lose them unnoticed, and the app version it last sent (`X-App-Version`;
 * none means an app before this stage). Additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->unsignedInteger('outbox_count')->nullable()->after('last_synced_at');
            $table->timestamp('outbox_reported_at')->nullable()->after('outbox_count');
            $table->string('app_version', 32)->nullable()->after('outbox_reported_at');
            $table->timestamp('app_version_reported_at')->nullable()->after('app_version');
        });
    }

    public function down(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->dropColumn(['outbox_count', 'outbox_reported_at', 'app_version', 'app_version_reported_at']);
        });
    }
};
