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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();

            // Contact person
            $table->string('contact_person')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('postal_code')->nullable();

            // Business details
            $table->string('tax_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('currency', 3)->default('USD');

            // Payment terms
            $table->integer('payment_terms_days')->default(30);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->decimal('current_balance', 15, 2)->default(0);

            // Performance metrics
            $table->decimal('total_orders', 15, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->integer('on_time_deliveries')->default(0);
            $table->integer('late_deliveries')->default(0);

            // Status and notes
            $table->string('status');
            $table->text('notes')->nullable();

            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index('code');
            $table->index(['country', 'city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
