<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invites (decision 4.4, stage 3): a person is invited to a business by email,
 * with a role and shops; an invite to an email that already has a login adds a
 * membership to it. Only the hash of the invite token is stored.
 *
 * Additive: a new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_invites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->json('shop_ids')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_invites');
    }
};
