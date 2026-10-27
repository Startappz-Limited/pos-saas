<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline rules (Phase 7, decision 4.9).
 *
 * - `sync_issues`: what a till's push turned up for an owner or manager to
 *   look at: a gap or a repeat in a till's invoice series, a customer whose
 *   phone matches another, a sale that took stock below zero or carries an
 *   implausible time. One row per thing (`key`), resolved by a person or, for
 *   a gap the till later fills, by the server.
 * - `customers.merged_into_id`: a duplicate merged into another customer is
 *   soft-deleted and points at the one kept, so a till that still names it
 *   (by its client uuid) reaches the kept one.
 *
 * Additive: a new table and a nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_issues', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('register_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40)->comment('invoice_gap, invoice_repeat, possible_duplicate_customer, negative_stock, implausible_time');
            $table->string('key')->comment('What the issue is about, so the same thing is recorded once');
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resolution', 40)->nullable()->comment('checked, kept, merged, filled');
            $table->timestamps();
            $table->unique(['business_id', 'type', 'key']);
            $table->index(['business_id', 'resolved_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('client_uuid')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });

        Schema::dropIfExists('sync_issues');
    }
};
