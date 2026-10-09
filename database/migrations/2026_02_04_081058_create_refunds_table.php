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
            $table->uuid('uuid')->unique();

            $table->foreignId('return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('refund_number')->unique();
            $table->string('method'); // RefundMethod enum
            $table->decimal('amount', 15, 2);

            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('notes')->nullable();

            $table->string('transaction_id')->nullable();
            $table->string('reference_number')->nullable();

            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index('refund_number');
            $table->index(['return_id', 'status']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
