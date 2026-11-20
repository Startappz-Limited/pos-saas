<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approved release of quarantined stock (stage 11, quarantine policy). Each release moves a quantity from
 * a quarantined layer to a new sellable layer that keeps the original batch and expiry; this row records who
 * approved it, when, why, and the figures as they were. request_key makes a retried release a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('variation_id')->constrained('product_variations');
            $table->foreignId('from_cost_layer_id')->constrained('cost_layers');
            $table->foreignId('to_cost_layer_id')->constrained('cost_layers');
            $table->decimal('quantity', 15, 3);
            $table->string('quarantine_status', 20);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('reason');
            $table->string('request_key', 64);
            $table->foreignId('approved_by')->constrained('users');
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->unique(['shop_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_releases');
    }
};
