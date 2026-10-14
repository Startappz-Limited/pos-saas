<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_approval_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('comments')->nullable();
            $table->timestamps();
            $table->index('expense_id');
            $table->unique('uuid');
            $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_approval_history');
    }
};
