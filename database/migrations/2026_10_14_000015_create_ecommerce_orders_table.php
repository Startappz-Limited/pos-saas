<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->string('platform');
            $table->string('platform_order_id');
            $table->string('order_number');
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->string('currency', 3)->default('KES');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('platform_created_at')->nullable();
            $table->timestamp('platform_updated_at')->nullable();
            $table->json('platform_data')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('platform_created_at');
            $table->index('sale_id');
            $table->unique(['shop_id', 'platform', 'platform_order_id']);
            $table->index('status');
            $table->unique('uuid');
            $table->foreign('converted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_orders');
    }
};
