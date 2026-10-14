<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->string('phone');
            $table->string('direction')->default('outbound');
            $table->string('message_type')->default('text');
            $table->text('content');
            $table->string('template_name')->nullable();
            $table->string('status')->default('pending');
            $table->string('whatsapp_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index('created_at');
            $table->index('phone');
            $table->index(['shop_id', 'customer_id']);
            $table->index(['shop_id', 'status']);
            $table->unique('uuid');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('sent_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
