<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->comment('Rows with no shop apply to the whole business');
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('campaign_type');
            $table->string('channel');
            $table->decimal('budget', 15, 2);
            $table->decimal('spent', 15, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('target_revenue', 15, 2)->nullable();
            $table->integer('target_conversions')->nullable();
            $table->integer('target_reach')->nullable();
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->integer('conversions')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('reach')->default(0);
            $table->string('status');
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('promo_code')->nullable();
            $table->boolean('auto_post_enabled')->default(false);
            $table->boolean('ai_assist_enabled')->default(false);
            $table->json('ai_settings')->nullable();
            $table->json('content_template')->nullable();
            $table->string('default_landing_url')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('channel');
            $table->index('code');
            $table->unique('code');
            $table->index('promo_code');
            $table->index(['shop_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
