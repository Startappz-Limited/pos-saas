<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A product's medical classification (stage 11, quarantine policy). Medical stock that is known to be
 * expired, or whose expiry is unverified, is never released for sale by approval. Existing products start
 * as non-medical: a business must classify its medical products before relying on the policy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_medical')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_medical');
        });
    }
};
