<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Held push items (stage 10, step 5c):
 *
 * - `sync_push_receipts.outcome`: `applied` (as today), `accepted` (applied by a
 *   manager on the till sync review) or `discarded` (removed on the review or
 *   from the till), so the till's next push of it, or the pull's
 *   `resolved_items`, tells the till to clear it. Existing rows are `applied`.
 * - `sales.sync_flags`: how a held sale was accepted (outside its lease, with an
 *   unusual number, as recorded, re-rung), shown on the sale page. Null on
 *   existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_push_receipts', function (Blueprint $table) {
            $table->string('outcome', 16)->default('applied')->after('result');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->json('sync_flags')->nullable()->after('void_recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sync_flags');
        });

        Schema::table('sync_push_receipts', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
