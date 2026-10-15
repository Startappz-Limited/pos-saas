<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_ecommerce_sync', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->string('platform');
            $table->string('platform_product_id')->nullable();
            $table->string('platform_name')->nullable();
            $table->string('platform_sku')->nullable();
            $table->string('sync_status')->default('pending');
            $table->string('sync_direction')->default('bidirectional');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_sync_attempt_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->json('platform_data')->nullable();
            $table->json('sync_metadata')->nullable();
            $table->boolean('auto_sync')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('last_synced_at');
            $table->unique(['shop_id', 'platform', 'platform_product_id'], 'product_ecommerce_sync_shop_platform_product_unique');
            $table->index(['sync_status', 'auto_sync']);
            $table->unique('uuid');
            $table->unique(['product_id', 'shop_id', 'platform'], 'unique_product_shop_platform');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_ecommerce_sync');
    }
};
