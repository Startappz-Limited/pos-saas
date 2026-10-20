<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data export and business closure (stage 3).
 *
 * - `business_exports`: an archive of a business's data, built in the
 *   background and downloaded by its owners.
 * - `business_closures`: a closure from request to erasure. It outlives the
 *   business on purpose (no foreign key to it): it records that, when and
 *   at whose request a business was erased.
 * - `owner_confirmations`: one-time codes emailed to an owner to confirm a
 *   closure (and, later in the stage, a handover). Only their hash is stored.
 *
 * Additive: new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose')->default('request')->comment('request or closure');
            $table->string('status')->default('pending')->comment('pending, ready or failed');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->json('summary')->nullable()->comment('rows per table');
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('business_closures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id')->index()->comment('No foreign key: the record outlives the business');
            $table->uuid('business_uuid');
            $table->string('business_name');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->default('owner')->comment('owner, or lapsed (Phase 4)');
            $table->timestamp('delete_after');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('erased_at')->nullable();
            $table->json('summary')->nullable()->comment('rows erased per table');
            $table->timestamps();
        });

        Schema::create('owner_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose')->comment('close, or handover');
            $table->string('code_hash');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'user_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_confirmations');
        Schema::dropIfExists('business_closures');
        Schema::dropIfExists('business_exports');
    }
};
