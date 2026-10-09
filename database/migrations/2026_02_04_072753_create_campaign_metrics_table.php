<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');

            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);

            // Calculated metrics
            $table->decimal('ctr', 8, 4)->default(0); // Click-through rate
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('cpc', 10, 2)->default(0); // Cost per click
            $table->decimal('cpa', 10, 2)->default(0); // Cost per acquisition
            $table->decimal('roas', 10, 4)->default(0); // Return on ad spend

            $table->timestamps();

            $table->unique(['campaign_id', 'metric_date']);
            $table->index('metric_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
    }
};
