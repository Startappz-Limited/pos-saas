<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('sale_id');
            $table->string('payment_number');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('received_by')->nullable();
            $table->uuid('client_uuid')->nullable()->comment('Set by the till that recorded it offline');
            $table->timestamps();
            $table->unique('payment_number');
            $table->index(['sale_id', 'paid_at']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->unique(['business_id', 'client_uuid']);
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
