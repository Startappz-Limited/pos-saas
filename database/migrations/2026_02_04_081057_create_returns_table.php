<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();

            $table->string('return_number')->unique();
            $table->string('status'); // ReturnStatus enum
            $table->string('reason'); // ReturnReason enum
            $table->text('notes')->nullable();

            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('restocking_fee', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);

            // Workflow
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users');
            $table->text('inspection_notes')->nullable();

            $table->timestamps();

            $table->index('uuid');
            $table->index('return_number');
            $table->index(['sale_id', 'status']);
            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};
