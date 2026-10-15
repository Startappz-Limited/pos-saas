<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('return_number');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('sale_return_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('supplier_credit_amount', 15, 2)->default(0);
            $table->string('supplier_credit_reference')->nullable();
            $table->string('shipment_reference')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('shipped_by')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['purchase_order_id', 'status']);
            $table->index('reason');
            $table->unique('return_number');
            $table->index(['shop_id', 'status']);
            $table->index('status');
            $table->index(['supplier_id', 'status']);
            $table->unique('uuid');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('cancelled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sale_return_id')->references('id')->on('returns')->nullOnDelete();
            $table->foreign('shipped_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
