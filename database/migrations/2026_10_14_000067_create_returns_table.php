<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->string('return_number');
            $table->string('status');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('restocking_fee', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->unsignedBigInteger('inspected_by')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->timestamps();
            $table->index('return_number');
            $table->unique('return_number');
            $table->index(['sale_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('inspected_by')->references('id')->on('users');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
