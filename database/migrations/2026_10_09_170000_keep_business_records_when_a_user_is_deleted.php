<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * References from business records to the person who did something.
     * Deleting that person (right to erasure) must keep the record and only
     * clear the reference, as ~90 other references already do (nullOnDelete).
     *
     * These did not: cash_registers and expense_approval_history cascaded, so
     * deleting a cashier deleted their register sessions and approvals; the
     * rest had no delete rule, so deleting anyone who had ever received stock
     * or raised a purchase order failed outright.
     *
     * Columns that belong to the person rather than the business (shop
     * links, saved reports, dashboard widgets, notification settings...) keep
     * cascading.
     *
     * @var array<string, array<int, string>>
     */
    private array $references = [
        'cash_registers' => ['user_id'],
        'expense_approval_history' => ['user_id'],
        'refunds' => ['processed_by'],
        'returns' => ['requested_by', 'approved_by', 'inspected_by'],
        'stock_intakes' => ['received_by', 'created_by', 'updated_by', 'completed_by'],
        'stock_adjustments' => ['created_by', 'approved_by'],
        'purchase_orders' => ['created_by', 'updated_by', 'approved_by'],
        'purchase_order_items' => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        foreach ($this->references as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->unsignedBigInteger($column)->nullable()->change();
                    $table->foreign($column)->references('id')->on('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Puts back the old delete rules (as "no action"; the two cascades are
     * not restored, since cascading deleted business records). Columns stay
     * nullable: rows may already have lost their user.
     */
    public function down(): void
    {
        foreach ($this->references as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on('users');
                }
            });
        }
    }
};
