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
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('delivery_fee', 10, 2)->default(0)->after('tax_amount');
            $table->decimal('packaging_fee', 10, 2)->default(0)->after('delivery_fee');
            $table->decimal('other_expenses', 10, 2)->default(0)->after('packaging_fee');
            $table->string('expense_notes')->nullable()->after('other_expenses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['delivery_fee', 'packaging_fee', 'other_expenses', 'expense_notes']);
        });
    }
};
