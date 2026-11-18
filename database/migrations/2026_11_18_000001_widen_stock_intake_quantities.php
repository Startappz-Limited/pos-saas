<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock intake quantities at three decimals, like every other stock quantity (stage 11, review
 * follow-up): a delivery of 2.5 kg or 0.125 kg was stored and received as a whole or two-decimal
 * figure. Widening decimal(15,2) to decimal(15,3) keeps every existing value exactly; no row changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_intakes', function (Blueprint $table) {
            $table->decimal('quantity_received', 15, 3)->change();
            $table->decimal('quantity_accepted', 15, 3)->default(0)->change();
            $table->decimal('quantity_rejected', 15, 3)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Narrowing back would round existing three-decimal figures, so it is not reversed automatically.
    }
};
