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
        Schema::table('stock_intakes', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('purchase_order_item_id')->nullable()->change();
            $table->foreignId('supplier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_intakes', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable(false)->change();
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }
};
