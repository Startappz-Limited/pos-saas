<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 11b, steps 2 and 6: a pricing rule may name one variation and one unit (both nullable: NULL is today's
 * meaning, every variation in any unit), and a customer-specific rule names a customer, not a staff user.
 *
 * Additive, with one check: `customer_id` used to point at `users`, so an existing rule can name a staff member. The
 * re-point refuses while any rule names an id that isn't a customer, rather than silently attaching it to someone else.
 * UAT has none; `pricing-rules:check-customers` lists any before this runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->unsignedBigInteger('variation_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('unit_id')->nullable()->after('variation_id');
            $table->foreign('variation_id')->references('id')->on('product_variations')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('product_units')->restrictOnDelete();
        });

        $misnamed = DB::table('pricing_rules')
            ->whereNotNull('customer_id')
            ->whereNotIn('customer_id', DB::table('customers')->select('id'))
            ->count();

        if ($misnamed > 0) {
            throw new RuntimeException("{$misnamed} pricing rule(s) name a user that is not a customer. Run php artisan pricing-rules:check-customers, fix them, then migrate again.");
        }

        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $notUsers = DB::table('pricing_rules')
            ->whereNotNull('customer_id')
            ->whereNotIn('customer_id', DB::table('users')->select('id'))
            ->count();

        if ($notUsers > 0) {
            throw new RuntimeException("{$notUsers} pricing rule(s) name a customer that is not a user; the rollback would leave them dangling.");
        }

        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropForeign(['customer_id']);
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropForeign(['variation_id']);
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['variation_id', 'unit_id']);
        });
    }
};
