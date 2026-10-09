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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method'); // cash, card, bank_transfer, mobile_money, etc.
            $table->string('reference')->nullable(); // Transaction reference
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['sale_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
