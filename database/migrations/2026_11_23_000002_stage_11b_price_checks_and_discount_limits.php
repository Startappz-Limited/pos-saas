<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 11b, steps 3 to 5: a sale line records the price the server expected and who allowed a lower one; a sale
 * records who approved a discount above the person's limit; a role carries its discount limit (NULL: no limit).
 * Additive. Past lines and sales weren't checked, so they stay NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('expected_unit_price', 12, 2)->nullable()->after('unit_price');
            $table->foreignId('price_overridden_by')->nullable()->after('expected_unit_price')->constrained('users')->nullOnDelete();
            $table->timestamp('override_approved_at')->nullable()->after('price_overridden_by');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->foreignId('discount_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('override_approved_at')->nullable();
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->decimal('max_discount_percent', 5, 2)->nullable()->after('guard_name');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('max_discount_percent');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign(['discount_approved_by']);
            $table->dropColumn(['discount_approved_by', 'override_approved_at']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['price_overridden_by']);
            $table->dropColumn(['expected_unit_price', 'price_overridden_by', 'override_approved_at']);
        });
    }
};
