<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO costing (stage 1b; design in docs/saas/fifo-analysis.md).
 *
 * cost_layers and cost_allocations were created by the original project but
 * never written to, so they're rebuilt for the SaaS design: per shop and per
 * variation, with the business, the layer's source and the allocation's
 * consumer, and provisional allocations for stock sold below zero.
 *
 * They hold no data anywhere; the migration refuses to run if they do.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->refuseIfNotEmpty();

        Schema::dropIfExists('cost_allocations');
        Schema::dropIfExists('cost_layers');

        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('product_variations')->cascadeOnDelete();
            $table->string('source_type')->comment('What created the layer: intake, adjustment, sale_return, opening');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('stock_intake_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_cost', 15, 2)->nullable()->comment('NULL: cost unknown');
            $table->integer('original_quantity');
            $table->integer('remaining_quantity');
            $table->timestamp('received_at')->comment('FIFO order: oldest first, then id');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'variation_id', 'remaining_quantity', 'received_at'], 'cost_layers_fifo_index');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variation_id')->constrained('product_variations')->cascadeOnDelete();
            $table->foreignId('cost_layer_id')->nullable()->constrained()->nullOnDelete()->comment('NULL while provisional');
            $table->string('consumer_type')->comment('What consumed the stock: sale_item, stock_adjustment_item, purchase_return_item');
            $table->unsignedBigInteger('consumer_id');
            $table->foreignId('sale_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->integer('quantity_allocated');
            $table->decimal('unit_cost', 15, 2)->nullable()->comment('NULL: cost unknown');
            $table->decimal('total_cost', 15, 2)->nullable();
            $table->boolean('is_provisional')->default(false)->comment('Sold below zero: costed at the last known cost until stock arrives');
            $table->decimal('provisional_unit_cost', 15, 2)->nullable()->comment('The estimate it carried before being re-costed');
            $table->timestamp('allocated_at');
            $table->timestamp('settled_at')->nullable()->comment('When a provisional allocation was re-costed from a layer');
            $table->timestamps();
            $table->index(['shop_id', 'variation_id', 'is_provisional'], 'cost_allocations_provisional_index');
            $table->index(['consumer_type', 'consumer_id']);
        });
    }

    public function down(): void
    {
        $this->refuseIfNotEmpty();

        Schema::dropIfExists('cost_allocations');
        Schema::dropIfExists('cost_layers');

        // The original (unused) shape.
        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_intake_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_cost', 15, 2);
            $table->integer('original_quantity');
            $table->integer('remaining_quantity');
            $table->date('received_date');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['product_id', 'shop_id', 'remaining_quantity']);
            $table->index('received_date');
        });

        Schema::create('cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cost_layer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_allocated');
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->timestamp('allocated_at');
            $table->timestamps();
            $table->index('sale_item_id');
            $table->index('cost_layer_id');
        });
    }

    private function refuseIfNotEmpty(): void
    {
        foreach (['cost_layers', 'cost_allocations'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("Refusing to rebuild {$table}: it holds rows. It was expected to be empty (never written before stage 1b).");
            }
        }
    }
};
