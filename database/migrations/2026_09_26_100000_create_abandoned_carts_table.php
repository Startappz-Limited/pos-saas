<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abandoned carts pushed by the WooCommerce "Abandoned Cart Recovery" plugin.
 *
 * One row per plugin cart per shop. `checkout_link` holds a LIVE signed recovery
 * URL that restores the customer's cart, so it is stored encrypted (the model
 * casts it) and is a TEXT column because ciphertext is far longer than the URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('platform')->default('woocommerce');
            $table->string('platform_cart_id');

            $table->string('status')->default('abandoned'); // AbandonedCartStatus
            $table->string('platform_status')->nullable(); // the plugin's own cart status

            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('phone_normalized', 20)->nullable()->index();
            $table->string('user_type')->nullable(); // GUEST | REGISTERED
            $table->string('capture_source')->nullable();

            $table->string('currency', 3)->default('KES');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('coupon_code')->nullable();
            $table->text('checkout_link')->nullable(); // encrypted at rest

            $table->timestamp('abandoned_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->string('platform_order_id')->nullable();
            $table->foreignId('ecommerce_order_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->unsignedInteger('reminders_sent')->default(0);
            $table->string('last_activity')->nullable();

            $table->json('platform_data')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'platform', 'platform_cart_id']);
            $table->index(['shop_id', 'status']);
            $table->index('abandoned_at');
            $table->index('platform_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abandoned_carts');
    }
};
