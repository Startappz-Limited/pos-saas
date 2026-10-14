<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->string('platform')->default('woocommerce');
            $table->string('platform_cart_id');
            $table->string('status')->default('abandoned');
            $table->string('platform_status')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('phone_normalized', 20)->nullable();
            $table->string('user_type')->nullable();
            $table->string('capture_source')->nullable();
            $table->string('currency', 3)->default('KES');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->text('checkout_link')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->string('platform_order_id')->nullable();
            $table->unsignedBigInteger('ecommerce_order_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->string('last_activity')->nullable();
            $table->json('platform_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('abandoned_at');
            $table->index('phone_normalized');
            $table->index('platform_order_id');
            $table->unique(['shop_id', 'platform', 'platform_cart_id']);
            $table->index(['shop_id', 'status']);
            $table->unique('uuid');
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('converted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('ecommerce_order_id')->references('id')->on('ecommerce_orders')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
