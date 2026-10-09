<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_intakes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('intake_number')->unique();

            // References
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();

            // Product Information
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->nullOnDelete();

            // Intake Details
            $table->string('status')->default('pending');
            $table->date('intake_date');
            $table->decimal('quantity_received', 15, 2);
            $table->decimal('quantity_accepted', 15, 2)->default(0);
            $table->decimal('quantity_rejected', 15, 2)->default(0);
            $table->string('unit')->default('pcs');

            // Quality Control
            $table->string('quality_status')->nullable(); // excellent, good, acceptable, poor, rejected
            $table->text('quality_notes')->nullable();
            $table->json('quality_checks')->nullable();

            // Location & Storage
            $table->string('storage_location')->nullable();
            $table->string('bin_location')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();

            // Receiver Information
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('received_at')->nullable();

            // Completion
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();

            // Additional Information
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();

            // Audit Fields
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('intake_date');
            $table->index('quality_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_intakes');
    }
};
