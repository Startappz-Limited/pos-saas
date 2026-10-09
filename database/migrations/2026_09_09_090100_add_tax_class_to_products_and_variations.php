<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product VAT treatment.
 *
 * NULL means "inherit" — the shop's default tax class, falling back to
 * config('tax.default_class'). Leaving existing rows NULL keeps every product on
 * the configured default rather than silently pinning them to today's value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tax_class')) {
                $table->string('tax_class', 32)->nullable()->after('wholesale_price');
            }
        });

        Schema::table('product_variations', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variations', 'tax_class')) {
                $table->string('tax_class', 32)->nullable()->after('wholesale_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_class');
        });

        Schema::table('product_variations', function (Blueprint $table) {
            $table->dropColumn('tax_class');
        });
    }
};
