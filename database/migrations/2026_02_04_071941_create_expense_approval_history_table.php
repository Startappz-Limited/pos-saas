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
            $table->uuid('uuid')->unique();

            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('action'); // submitted, approved, rejected, returned
            $table->string('from_status');
            $table->string('to_status');
            $table->text('comments')->nullable();

            $table->timestamps();

            $table->index('expense_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_approval_history');
    }
};
