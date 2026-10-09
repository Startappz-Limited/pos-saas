<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line VAT, captured at the moment of sale.
 *
 * A tax invoice has to show the tax charged per line and its rate, and the VAT
 * return needs turnover split by tax class. Both are snapshots: changing a
 * product's tax class later must not rewrite history.
 *
 * `taxable_amount` is the line net of VAT. Under VAT-inclusive pricing it is
 * `line_total - tax_amount`; under exclusive pricing it equals `line_total`.
 * Existing rows default to zero tax on the full line, which matches the legacy
 * behaviour where no VAT was ever computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'tax_class')) {
                $table->string('tax_class', 32)->nullable()->after('line_total');
            }
            if (! Schema::hasColumn('sale_items', 'tax_rate')) {
                $table->decimal('tax_rate', 6, 3)->default(0)->after('tax_class');
            }
            if (! Schema::hasColumn('sale_items', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_rate');
            }
            if (! Schema::hasColumn('sale_items', 'taxable_amount')) {
                $table->decimal('taxable_amount', 12, 2)->default(0)->after('tax_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['tax_class', 'tax_rate', 'tax_amount', 'taxable_amount']);
        });
    }
};
