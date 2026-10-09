<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type'); // AlertType enum
            $table->string('category'); // AlertCategory enum

            // Conditions
            $table->json('conditions');
            $table->string('operator')->default('AND'); // AND, OR

            // Thresholds
            $table->decimal('threshold_value', 15, 2)->nullable();
            $table->string('threshold_unit')->nullable();

            // Actions
            $table->json('channels'); // ['email', 'sms', 'in_app']
            $table->json('recipient_roles')->nullable();
            $table->json('recipient_users')->nullable();

            // Schedule
            $table->string('frequency')->nullable(); // immediate, hourly, daily, weekly
            $table->time('send_time')->nullable();
            $table->json('send_days')->nullable(); // [1,2,3,4,5] for weekdays

            $table->boolean('is_active')->default(true);

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('uuid');
            $table->index(['shop_id', 'type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
