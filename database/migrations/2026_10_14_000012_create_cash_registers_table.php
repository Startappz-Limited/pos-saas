<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete()->comment('The till this session ran on');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('register_number');
            $table->date('register_date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('expense_opening_balance', 15, 2)->default(0);
            $table->decimal('expense_balance_used', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_cash_sales', 15, 2)->default(0);
            $table->decimal('total_card_sales', 15, 2)->default(0);
            $table->decimal('total_credit_sales', 15, 2)->default(0);
            $table->decimal('total_mobile_money_sales', 15, 2)->default(0);
            $table->decimal('total_bank_transfer_sales', 15, 2)->default(0);
            $table->decimal('total_cheque_sales', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->uuid('client_uuid')->nullable()->comment('Set by the till that opened it offline');
            $table->timestamps();
            $table->unique('register_number');
            $table->index(['shop_id', 'register_date', 'status']);
            $table->index(['status', 'opened_at']);
            $table->unique('uuid');
            $table->unique(['business_id', 'client_uuid']);
            $table->foreign('closed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
