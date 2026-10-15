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
            $table->foreignId('business_id')->constrained()->comment('Rows with no shop apply to the whole business');
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('category');
            $table->json('conditions');
            $table->string('operator')->default('AND');
            $table->decimal('threshold_value', 15, 2)->nullable();
            $table->string('threshold_unit')->nullable();
            $table->json('channels');
            $table->json('recipient_roles')->nullable();
            $table->json('recipient_users')->nullable();
            $table->string('frequency')->nullable();
            $table->time('send_time')->nullable();
            $table->json('send_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'type', 'is_active']);
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_rules');
    }
};
