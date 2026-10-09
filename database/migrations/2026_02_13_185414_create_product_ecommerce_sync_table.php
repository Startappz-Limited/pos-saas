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
        Schema::create('product_ecommerce_sync', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('platform'); // woocommerce, shopify
            $table->string('platform_product_id')->nullable(); // ID on the e-commerce platform
            $table->string('platform_name')->nullable(); // Alias/name on that platform
            $table->string('platform_sku')->nullable(); // SKU on platform (should match local SKU)
            $table->string('sync_status')->default('pending'); // pending, synced, error, out_of_sync
            $table->string('sync_direction')->default('bidirectional'); // to_platform, from_platform, bidirectional
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('platform_data')->nullable(); // Additional platform-specific data
            $table->json('sync_metadata')->nullable(); // Sync history, change tracking
            $table->boolean('auto_sync')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['product_id', 'shop_id', 'platform'], 'unique_product_shop_platform');
            $table->index(['platform', 'platform_product_id']);
            $table->index(['sync_status', 'auto_sync']);
            $table->index('last_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_ecommerce_sync');
    }
};
