<?php

namespace App\Support\Stock;

use Illuminate\Support\Facades\DB;

/**
 * Before stage 10 a decrease adjustment's lines saved by the form held a
 * positive quantity (the adjustment's type gave the direction); imported ones
 * were already negative. Lines are signed now, so this negates the positive
 * lines of decrease adjustments, and nothing else. Running it again changes
 * nothing: no positive decrease line is left. Used by the step 1g migration.
 */
final class LegacyAdjustmentSigns
{
    /**
     * @return int the lines negated
     */
    public function fix(): int
    {
        return DB::table('stock_adjustment_items')
            ->where('quantity_change', '>', 0)
            ->whereIn('stock_adjustment_id', DB::table('stock_adjustments')->where('type', 'decrease')->select('id'))
            ->update(['quantity_change' => DB::raw('-quantity_change')]);
    }
}
