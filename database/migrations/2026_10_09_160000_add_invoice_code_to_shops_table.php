<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop codes are typed in by users and only unique within a business, so
     * on their own they cannot keep invoice numbers unique across businesses.
     * Each new shop gets a generated 3-character invoice code
     * (Shop::assignInvoiceCode), unique together with the shop code across all
     * businesses: INV-MAIN-7KQ-2026-000123.
     *
     * Existing shops keep their current format (no code) until their shop code
     * changes. Their codes were unique system-wide when they were created, and
     * the extra segment means the two formats cannot produce the same number.
     *
     * Invoice numbers are therefore unique system-wide again by construction,
     * so the system-wide key on sales.invoice_number comes back.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->string('invoice_code', 3)->nullable()->after('code');
            $table->unique(['code', 'invoice_code']);
        });

        $duplicates = DB::table('sales')
            ->select('invoice_number')
            ->groupBy('invoice_number')
            ->havingRaw('count(*) > 1')
            ->limit(5)
            ->pluck('invoice_number');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot restore the system-wide unique key on sales.invoice_number; these numbers are used more than once: '
                .$duplicates->implode(', ')
            );
        }

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['shop_id', 'invoice_number']);
            $table->unique(['invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->unique(['shop_id', 'invoice_number']);
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropUnique(['code', 'invoice_code']);
            $table->dropColumn('invoice_code');
        });
    }
};
