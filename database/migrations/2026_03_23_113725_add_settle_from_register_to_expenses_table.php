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
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('settled_from_register')->default(false)->after('is_paid');
            $table->foreignId('cash_register_id')->nullable()->after('settled_from_register')
                ->constrained('cash_registers')->nullOnDelete();
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->decimal('expense_balance_used', 15, 2)->default(0)->after('expense_opening_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['cash_register_id']);
            $table->dropColumn(['settled_from_register', 'cash_register_id']);
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('expense_balance_used');
        });
    }
};
