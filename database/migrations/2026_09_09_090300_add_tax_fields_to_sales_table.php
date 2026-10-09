<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sale-level VAT metadata.
 *
 * `tax_inclusive` records which arithmetic produced `total_amount`, because the
 * two conventions differ and a sale must still add up years later:
 *
 *   inclusive  total = subtotal - discount + fees          (VAT sits inside subtotal)
 *   exclusive  total = subtotal - discount + tax + fees    (VAT added on top)
 *
 * Defaulting to false leaves every historical sale on the exclusive formula,
 * which is exactly how they were calculated.
 *
 * `customer_tax_pin` is snapshotted so a reissued invoice keeps the PIN that was
 * on the original, and `tax_breakdown` stores the per-class totals the invoice
 * and the VAT return need without re-aggregating the lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'tax_inclusive')) {
                $table->boolean('tax_inclusive')->default(false)->after('tax_amount');
            }
            if (! Schema::hasColumn('sales', 'taxable_amount')) {
                $table->decimal('taxable_amount', 15, 2)->default(0)->after('tax_inclusive');
            }
            if (! Schema::hasColumn('sales', 'tax_breakdown')) {
                $table->json('tax_breakdown')->nullable()->after('taxable_amount');
            }
            if (! Schema::hasColumn('sales', 'customer_tax_pin')) {
                $table->string('customer_tax_pin', 32)->nullable()->after('walk_in_customer_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tax_inclusive', 'taxable_amount', 'tax_breakdown', 'customer_tax_pin']);
        });
    }
};
