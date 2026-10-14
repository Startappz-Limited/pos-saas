<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->string('refund_number');
            $table->string('method');
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('processed_by');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index('refund_number');
            $table->unique('refund_number');
            $table->index(['return_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('processed_by')->references('id')->on('users');
            $table->foreign('return_id')->references('id')->on('returns')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
