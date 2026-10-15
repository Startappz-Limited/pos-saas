<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('social_account_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('platform');
            $table->string('status')->default('draft');
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('call_to_action')->nullable();
            $table->json('media_urls')->nullable();
            $table->string('landing_url')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->json('ai_prompt_context')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->string('external_post_url')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('reactions')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['campaign_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index(['status', 'scheduled_at']);
            $table->unique('uuid');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('social_account_id')->references('id')->on('social_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_posts');
    }
};
