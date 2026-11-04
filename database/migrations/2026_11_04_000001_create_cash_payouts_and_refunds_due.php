<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Money a void hands back (stage 10, step 4; decided 4 November 2026). New
 * tables, so no data to carry across.
 *
 * - `cash_payouts`: cash taken out of an open session (a void's cash refund,
 *   a refund owed paid later, petty cash); the session's expected cash drops
 *   by it. The session names its register, so the payout has no register.
 * - `refunds_due`: money a voided sale took that is still owed back: cash when
 *   the person voiding had no open session of their own in the shop (or the
 *   void was an approved request), and every card, M-Pesa, bank transfer or
 *   cheque payment. Paid later (cash out of the payer's own session), or, for
 *   a cheque, settled as returned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('reason', 255);
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['cash_register_id', 'created_at']);
        });

        Schema::create('refunds_due', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->constrained('sale_payments')->nullOnDelete();
            // void, and later late_payment and duplicate_payment (step 5c)
            $table->string('source', 32);
            // cash, card, mobile_money, bank_transfer, cheque
            $table->string('method', 32);
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable();
            // pending, paid, settled
            $table->string('status')->default('pending');
            // paid, cheque_returned
            $table->string('resolution', 32)->nullable();
            $table->string('resolved_method', 32)->nullable();
            $table->string('resolved_reference')->nullable();
            $table->foreignId('cash_payout_id')->nullable()->constrained('cash_payouts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds_due');
        Schema::dropIfExists('cash_payouts');
    }
};
