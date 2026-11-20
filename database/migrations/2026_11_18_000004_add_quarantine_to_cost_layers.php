<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quarantine state on a stock layer (stage 11, quarantine policy). A quarantined layer is never sold, and
 * leaves quarantine only through an approved release, which creates a new sellable layer (stock_release_id)
 * that keeps the batch and expiry. stock_release_id is a plain column: stock_releases refers back to these
 * layers, so a foreign key here would be circular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_layers', function (Blueprint $table) {
            $table->string('quarantine_status', 20)->nullable()->index();
            $table->string('quarantine_reason')->nullable();
            $table->timestamp('quarantine_verified_at')->nullable();
            $table->foreignId('quarantine_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('stock_release_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('cost_layers', function (Blueprint $table) {
            $table->dropForeign(['quarantine_verified_by']);
            $table->dropIndex(['quarantine_status']);
            $table->dropIndex(['stock_release_id']);
            $table->dropColumn(['quarantine_status', 'quarantine_reason', 'quarantine_verified_at', 'quarantine_verified_by', 'stock_release_id']);
        });
    }
};
