<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product may have no wholesale price at all.
 *
 * `products.wholesale_price` shipped as NOT NULL DEFAULT 0 while the form,
 * both FormRequests and the API controller all validate it as `nullable` —
 * so an empty "Wholesale Price" field became NULL and the insert died with
 * "Column 'wholesale_price' cannot be null". `product_variations` and the
 * `shop_product` override pivot were already nullable; this brings the parent
 * column in line. NULL means "no wholesale price", which the till already
 * treats as "fall back to the retail price" — unlike 0, which would ring up
 * a wholesale customer at nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('wholesale_price', 10, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('wholesale_price', 10, 2)->default(0)->nullable(false)->change();
        });
    }
};
