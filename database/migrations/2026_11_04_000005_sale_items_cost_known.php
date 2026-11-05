<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lines whose cost isn't fully known (stage 10, step 6): `cost_known` is true
 * when every unit was costed from a batch with a cost, or from a known
 * estimate. Backfilled false for lines with an allocation of unknown cost (its
 * own cost NULL, or its batch's), counts printed before and after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->boolean('cost_known')->default(true)->after('profit_margin');
        });

        $unknown = DB::table('cost_allocations')
            ->leftJoin('cost_layers', 'cost_layers.id', '=', 'cost_allocations.cost_layer_id')
            ->whereNotNull('cost_allocations.sale_item_id')
            ->where(fn ($query) => $query->whereNull('cost_allocations.unit_cost')
                ->orWhere(fn ($layer) => $layer->whereNotNull('cost_allocations.cost_layer_id')->whereNull('cost_layers.unit_cost')))
            ->distinct()->pluck('cost_allocations.sale_item_id');

        $lines = DB::table('sale_items')->count();
        foreach ($unknown->chunk(500) as $ids) {
            DB::table('sale_items')->whereIn('id', $ids->all())->update(['cost_known' => false]);
        }

        if (! app()->runningUnitTests()) {
            echo "  Sale lines: {$lines}; marked cost unknown: ".$unknown->count().'; cost known: '.($lines - $unknown->count()).".\n";
        }
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('cost_known');
        });
    }
};
