<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            // Add additional payment method totals after total_credit_sales
            $table->decimal('total_mobile_money_sales', 15, 2)->default(0)->after('total_credit_sales');
            $table->decimal('total_bank_transfer_sales', 15, 2)->default(0)->after('total_mobile_money_sales');
            $table->decimal('total_cheque_sales', 15, 2)->default(0)->after('total_bank_transfer_sales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn([
                'total_mobile_money_sales',
                'total_bank_transfer_sales',
                'total_cheque_sales',
            ]);
        });
    }
};
