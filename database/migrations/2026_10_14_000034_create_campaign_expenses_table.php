<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('expense_id')->nullable();
            $table->string('expense_type');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('campaign_id');
            $table->index('expense_type');
            $table->unique('uuid');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('expense_id')->references('id')->on('expenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_expenses');
    }
};
