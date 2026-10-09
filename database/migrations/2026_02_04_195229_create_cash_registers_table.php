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
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Cashier who opened

            // Register session details
            $table->string('register_number')->unique();
            $table->date('register_date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->decimal('expected_balance', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable(); // Difference between expected and actual

            // Transaction totals for this register session
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_cash_sales', 15, 2)->default(0);
            $table->decimal('total_card_sales', 15, 2)->default(0);
            $table->decimal('total_credit_sales', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0);

            // Status and timestamps
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            // Notes
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['shop_id', 'register_date', 'status']);
            $table->index(['status', 'opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
