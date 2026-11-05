<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Void requests (decided 3 November 2026, stage 10, step 2): someone without
 * `sales.void` asks, with a reason, inside the sale's void window; someone
 * holding it approves (which voids the sale) or rejects. The window is checked
 * at `requested_at`; an approval counts until `expires_at` (48 hours later).
 * New table: no data to carry across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_void_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            // pending, approved, rejected, cancelled, expired
            $table->string('status')->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('expires_at');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_void_requests');
    }
};
