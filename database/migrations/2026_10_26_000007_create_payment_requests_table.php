<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-Pesa at the till (Phase 7, decisions 4.9 and open question 2): one row
 * per STK Push a till asked for, or per M-Pesa code a cashier entered on an
 * offline till. A payment counts only once the M-Pesa API, queried with the
 * shop's own account, says it was made; it is then applied to its sale once.
 *
 * The sale is named by its client uuid, so a payment confirmed before the
 * till has pushed the sale waits for it. An M-Pesa receipt is unique on the
 * platform, so one payment can't pay two sales.
 *
 * New table; no existing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('client_uuid')->comment('The till\'s own id for the request');
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('method', 20)->comment('stk, or code: entered on an offline till');
            $table->uuid('sale_client_uuid')->nullable()->comment('The sale it pays, by the till\'s id for it');
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->comment('pending, confirmed, failed, unmatched');
            $table->string('shortcode', 20);
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('merchant_request_id')->nullable();
            $table->string('mpesa_receipt', 40)->nullable()->unique();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('sale_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('applied_at')->nullable()->comment('When it was recorded against its sale');
            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('checks')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'client_uuid']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'sale_client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};
