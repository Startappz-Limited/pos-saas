<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_conversions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('conversion_type');
            $table->decimal('revenue', 15, 2)->default(0);
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('converted_at');
            $table->timestamps();
            $table->index('campaign_id');
            $table->index('converted_at');
            $table->unique('uuid');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_conversions');
    }
};
