<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shop a business opens into (stage 10a, step 3): set to the first shop
 * at sign-up. Matters only on the Free Trial (it can't be deleted or
 * deactivated there until another is chosen); on any other tier it is kept
 * but used for nothing. `businesses:set-default-shop` backfills existing
 * businesses with their oldest active shop.
 *
 * No foreign key: `shops.business_id` already points at `businesses`, and
 * `shops.manager_id` at `users`, which `users.default_business_id` points
 * back at `businesses` — a real constraint here would close a 3-table cycle
 * (businesses -> shops -> users -> businesses) that SQLite's schema builder
 * refuses to rebuild ("Foreign keys form a cycle"). A deleted shop is never
 * hard-deleted (soft delete only), so the column is never left dangling in
 * practice; `DeleteShopAction`/`ShopService::deactivate()` also refuse to
 * touch the Free Trial's sole default shop at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedBigInteger('default_shop_id')->nullable()->after('primary_owner_id');
            $table->index('default_shop_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('default_shop_id');
        });
    }
};
