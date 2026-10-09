<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase cost must be able to say "unknown".
 *
 * `cost_price` was NOT NULL DEFAULT 0, so a product whose purchase cost had
 * never been established was indistinguishable from one that genuinely costs
 * nothing. That ambiguity is what let the e-commerce importers paper over the
 * gap by writing a *selling* price into the cost column. Allowing NULL gives
 * the domain an honest "not set yet" state that reports and the cost-entry
 * screen can surface instead of silently mis-costing a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->nullable()->change();
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->default(0)->nullable(false)->change();
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->decimal('cost_price', 10, 2)->default(0)->nullable(false)->change();
        });
    }
};
