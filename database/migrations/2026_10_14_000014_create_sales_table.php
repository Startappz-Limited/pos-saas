<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('register_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->text('delivery_location')->nullable();
            $table->string('walk_in_customer_name')->nullable();
            $table->string('walk_in_customer_email')->nullable();
            $table->string('walk_in_customer_phone')->nullable();
            $table->string('customer_tax_pin', 32)->nullable();
            $table->unsignedBigInteger('delivery_company_id')->nullable();
            $table->string('invoice_number');
            $table->string('sale_type');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('discount_type')->nullable();
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('taxable_amount', 15, 2)->default(0);
            $table->json('tax_breakdown')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('packaging_fee', 10, 2)->default(0);
            $table->decimal('other_expenses', 10, 2)->default(0);
            $table->string('expense_notes')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_due', 15, 2)->default(0);
            $table->string('payment_status');
            $table->string('payment_method')->nullable();
            $table->boolean('is_cod')->default(false);
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->uuid('client_uuid')->nullable()->comment('Set by the till that recorded it offline');
            $table->boolean('needs_review')->default(false)->comment('Recorded time implausible (future, or before the register last synced)');
            $table->timestamps();
            $table->softDeletes();
            $table->index('invoice_number');
            $table->unique(['business_id', 'invoice_number']);
            $table->index('payment_status');
            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'status']);
            $table->index(['status', 'completed_at']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->unique(['business_id', 'client_uuid']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('delivery_company_id')->references('id')->on('delivery_companies');
            $table->foreign('register_id')->references('id')->on('cash_registers')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('source_id')->references('id')->on('sale_sources');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('voided_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
