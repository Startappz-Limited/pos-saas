<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 11c, step 4: every stock movement names the batches it changed (`layers`), so a past day's stock and value
 * can be rebuilt exactly from the ledger; and each business records when its ledger began (`ledger_started_at`), the
 * first batch's time. Additive: existing movements keep no `layers` (an unrebuildable window is marked, see
 * CreateInventorySnapshot), and the ledger start is filled by `stock:ledger-start`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->json('layers')->nullable()->after('unit_cost');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestamp('ledger_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('ledger_started_at');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('layers');
        });
    }
};
