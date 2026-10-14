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
            $table->unsignedBigInteger('campaign_id');
            $table->date('metric_date');
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->decimal('ctr', 8, 4)->default(0);
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('cpc', 10, 2)->default(0);
            $table->decimal('cpa', 10, 2)->default(0);
            $table->decimal('roas', 10, 4)->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'metric_date']);
            $table->index('metric_date');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
    }
};
