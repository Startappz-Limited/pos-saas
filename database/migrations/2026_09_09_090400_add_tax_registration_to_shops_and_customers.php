<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tax registration details.
 *
 * A Kenyan tax invoice must carry the seller's KRA PIN, and — for a supply to
 * another registered business — the buyer's PIN as well.
 *
 * `vat_registered` gates the whole VAT engine per shop. It defaults to false so
 * that deploying this migration changes nothing until a shop is deliberately
 * flagged; an unregistered business must not charge VAT.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            if (! Schema::hasColumn('shops', 'tax_pin')) {
                $table->string('tax_pin', 32)->nullable()->after('email');
            }
            if (! Schema::hasColumn('shops', 'vat_registered')) {
                $table->boolean('vat_registered')->default(false)->after('tax_pin');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'tax_pin')) {
                $table->string('tax_pin', 32)->nullable()->after('email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['tax_pin', 'vat_registered']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('tax_pin');
        });
    }
};
