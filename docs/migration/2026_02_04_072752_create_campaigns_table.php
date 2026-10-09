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
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();

            // Campaign details
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('campaign_type'); // CampaignType enum
            $table->string('channel'); // MarketingChannel enum

            // Budget
            $table->decimal('budget', 15, 2);
            $table->decimal('spent', 15, 2)->default(0);
            $table->string('currency', 3)->default('KES');

            // Duration
            $table->date('start_date');
            $table->date('end_date');

            // Goals
            $table->decimal('target_revenue', 15, 2)->nullable();
            $table->integer('target_conversions')->nullable();
            $table->integer('target_reach')->nullable();

            // Results (calculated/updated)
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->integer('conversions')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('reach')->default(0);

            // Status
            $table->string('status'); // CampaignStatus enum

            // Tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('promo_code')->nullable();

            $table->text('notes')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('uuid');
            $table->index('code');
            $table->index('promo_code');
            $table->index(['shop_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
