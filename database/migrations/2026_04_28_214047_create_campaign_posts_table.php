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
            $table->uuid('uuid')->unique();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->string('platform'); // SocialPlatform enum value
            $table->string('status')->default('draft'); // PostStatus enum value

            // Content
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('call_to_action')->nullable();
            $table->json('media_urls')->nullable(); // image/video URLs (from product images)
            $table->string('landing_url')->nullable(); // product purchase URL

            // AI-generated metadata
            $table->boolean('ai_generated')->default(false);
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->json('ai_prompt_context')->nullable();

            // Scheduling and publishing
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_post_id')->nullable();
            $table->string('external_post_url')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            // Lightweight engagement metrics fetched from platform
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('reactions')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_id', 'status']);
            $table->index(['shop_id', 'status']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_posts');
    }
};
