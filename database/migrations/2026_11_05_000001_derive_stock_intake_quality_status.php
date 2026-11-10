<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An intake's quality status is derived from its quantities (decided 5 November
 * 2026; StockIntakeQualityStatus::fromQuantities): nothing rejected is passed,
 * some of each is partial, nothing accepted is failed. Recomputes every intake,
 * soft-deleted ones too, replacing the old values (excellent, good, acceptable,
 * poor, rejected) and any status that disagreed with the quantities. Idempotent;
 * counts printed per status.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statuses = [
            'passed' => fn ($query) => $query->where(fn ($q) => $q->whereNull('quantity_rejected')->orWhere('quantity_rejected', '<=', 0)),
            'partial' => fn ($query) => $query->where('quantity_rejected', '>', 0)->where('quantity_accepted', '>', 0),
            'failed' => fn ($query) => $query->where('quantity_rejected', '>', 0)->where(fn ($q) => $q->whereNull('quantity_accepted')->orWhere('quantity_accepted', '<=', 0)),
        ];

        $counts = [];
        foreach ($statuses as $status => $matching) {
            $matching(DB::table('stock_intakes'))->where(fn ($q) => $q->whereNull('quality_status')->orWhere('quality_status', '!=', $status))
                ->update(['quality_status' => $status]);
            $counts[] = $status.': '.$matching(DB::table('stock_intakes'))->count();
        }

        if (! app()->runningUnitTests()) {
            echo '  Stock intakes by quality status: '.implode('; ', $counts).".\n";
        }
    }

    /**
     * The old values can't be restored: they disagreed with the quantities.
     */
    public function down(): void {}
};
