<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspending a business from the platform console (Phase 5, B1). The status
 * itself is a value of `businesses.status` ("suspended"), which is already a
 * string; these columns record who suspended it, when and why, and the status
 * reactivating it returns to.
 *
 * Additive: every existing business keeps its status, with nothing recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (! Schema::hasColumn('businesses', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('status');
                $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
                $table->string('suspension_reason', 500)->nullable()->after('suspended_by');
                $table->string('status_before_suspension', 30)->nullable()->after('suspension_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason', 'status_before_suspension']);
        });
    }
};
