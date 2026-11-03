<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a stock adjustment was settled (stage 10, step 1g follow-up), all
 * additive with defaults, so existing rows keep their meaning:
 *
 * - `auto_applied`: applied on saving by a creator holding the approve
 *   permission; false for every existing row (how they were settled isn't
 *   recorded, and they don't count towards today's threshold);
 * - `self_approved` and `approval_note`: an owner approving their own because no
 *   other member could, with the required note;
 * - `cost_value`: Σ |quantity| × unit cost when applied, for the day's
 *   cumulative threshold check; null on existing rows;
 * - `reminded_at`: the one reminder after 24 hours pending;
 * - `cancelled_by` and `cancelled_at`: a cancelled adjustment keeps its number
 *   (status `cancelled`), so the series stays without gaps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->boolean('auto_applied')->default(false)->after('status');
            $table->boolean('self_approved')->default(false)->after('approved_at');
            $table->text('approval_note')->nullable()->after('self_approved');
            $table->decimal('cost_value', 12, 2)->nullable()->after('approval_note');
            $table->timestamp('reminded_at')->nullable()->after('cost_value');
            $table->foreignId('cancelled_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->index(['shop_id', 'created_by', 'auto_applied', 'approved_at'], 'stock_adjustments_daily_auto_index');
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex('stock_adjustments_daily_auto_index');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['auto_applied', 'self_approved', 'approval_note', 'cost_value', 'reminded_at', 'cancelled_at']);
        });
    }
};
