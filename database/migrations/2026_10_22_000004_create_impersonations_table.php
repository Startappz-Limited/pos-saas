<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The impersonation log (Phase 5, B3): every time a platform operator signed
 * in as a business user, why, for how long, and how it ended. Kept as long as
 * the people and business it names (rows go with them).
 *
 * A new table: no data to carry across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('operator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 20)->nullable()->comment('manual, expired or signed_out');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 480)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'started_at']);
            $table->index(['operator_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
