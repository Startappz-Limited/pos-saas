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
            $table->uuid('uuid')->unique();

            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('conversion_type'); // ConversionType enum
            $table->decimal('revenue', 15, 2)->default(0);
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('converted_at');
            $table->timestamps();

            $table->index('campaign_id');
            $table->index('converted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_conversions');
    }
};
