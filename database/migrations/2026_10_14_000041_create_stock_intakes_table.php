<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_intakes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->string('intake_number');
            $table->unsignedBigInteger('purchase_order_id')->nullable();
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_variation_id')->nullable();
            $table->string('status')->default('pending');
            $table->date('intake_date');
            $table->decimal('quantity_received', 15, 2);
            $table->decimal('quantity_accepted', 15, 2)->default(0);
            $table->decimal('quantity_rejected', 15, 2)->default(0);
            $table->string('unit')->default('pcs');
            $table->string('quality_status')->nullable();
            $table->text('quality_notes')->nullable();
            $table->json('quality_checks')->nullable();
            $table->string('storage_location')->nullable();
            $table->string('bin_location')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('received_by');
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index('intake_date');
            $table->unique('intake_number');
            $table->index('quality_status');
            $table->index('status');
            $table->unique('uuid');
            $table->foreign('completed_by')->references('id')->on('users');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variation_id')->references('id')->on('product_variations')->nullOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->cascadeOnDelete();
            $table->foreign('received_by')->references('id')->on('users');
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_intakes');
    }
};
