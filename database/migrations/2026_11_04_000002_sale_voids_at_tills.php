<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voids from the till (stage 10, step 5c): the till a sale was voided at
 * (`voided_register_id`), whose session pays its cash, and the time the till
 * recorded an offline void (`void_recorded_at`; `voided_at` stays the
 * server's time). Additive and nullable: existing voids have neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('voided_register_id')->nullable()->after('voided_by')->constrained('registers')->nullOnDelete();
            $table->timestamp('void_recorded_at')->nullable()->after('voided_at');
            $table->index(['shop_id', 'status', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'status', 'voided_at']);
            $table->dropConstrainedForeignId('voided_register_id');
            $table->dropColumn('void_recorded_at');
        });
    }
};
