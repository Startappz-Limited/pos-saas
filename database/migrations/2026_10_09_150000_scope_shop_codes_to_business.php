<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shop code only has to be unique within its business: two businesses
     * may both have a "MAIN" shop.
     *
     * Invoice numbers embed the shop code (INV-MAIN-2026-000123, see
     * InvoiceNumberService) and are numbered per shop, so once codes repeat
     * across businesses so do invoice numbers. They were unique system-wide,
     * which would have failed the second business's first sale at the till;
     * they now only need to be unique per shop, which the per-shop gapless
     * sequence already guarantees.
     */
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropUnique(['code']);
            $table->unique(['business_id', 'code']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->unique(['shop_id', 'invoice_number']);
        });
    }

    /**
     * Fails if two businesses already share a shop code (or two shops an
     * invoice number), which is the point: the global key cannot come back
     * while that data exists.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['shop_id', 'invoice_number']);
            $table->unique(['invoice_number']);
        });

        Schema::table('shops', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'code']);
            $table->unique(['code']);
        });
    }
};
