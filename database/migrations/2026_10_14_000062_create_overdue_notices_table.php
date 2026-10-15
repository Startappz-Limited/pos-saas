<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overdue_notices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('credit_account_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('notice_number');
            $table->string('type');
            $table->integer('notice_level')->default(1);
            $table->decimal('overdue_amount', 15, 2);
            $table->integer('days_overdue');
            $table->string('delivery_method');
            $table->boolean('is_sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('message')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['credit_account_id', 'created_at']);
            $table->unique('notice_number');
            $table->index('uuid');
            $table->unique('uuid');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('credit_account_id')->references('id')->on('credit_accounts')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overdue_notices');
    }
};
