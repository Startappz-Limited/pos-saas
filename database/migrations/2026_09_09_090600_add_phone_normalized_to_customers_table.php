<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A canonical, dialable form of the customer's phone number.
 *
 * `phone` keeps whatever the cashier typed — "0712 345 678", "+254712345678",
 * "712345678" — because that is what staff recognise on screen. Those three
 * strings are the same subscriber, so matching on `phone` creates duplicate
 * customers and cannot be used as a key for WhatsApp or mobile money.
 *
 * `phone_normalized` holds the MSISDN ("254712345678"), maintained on write by
 * the model. Backfill existing rows with `customers:normalize-phones --apply`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'phone_normalized')) {
                $table->string('phone_normalized', 20)->nullable()->after('phone');
                $table->index('phone_normalized');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
