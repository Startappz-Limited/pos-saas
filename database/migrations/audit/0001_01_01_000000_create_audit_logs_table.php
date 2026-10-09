<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'audit';

    public function up(): void
    {
        Schema::connection('audit')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Who performed the action
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            // What was affected
            $table->string('auditable_type'); // Model class
            $table->unsignedBigInteger('auditable_id');
            $table->uuid('auditable_uuid')->nullable();

            // The action
            $table->string('event'); // created, updated, deleted, restored, etc.

            // Outcome of the operation. Enum-backed (App\Enums\AuditStatus) so a
            // failed attempt is as visible as a successful one — mandated by
            // .ai/general/0.5 audit_log_guide.md §5.7.
            $table->string('status')->default('success');

            // The changes
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('tags')->nullable();

            // Shop context (if applicable)
            $table->unsignedBigInteger('shop_id')->nullable();

            $table->timestamp('created_at');

            // Indexes for efficient querying
            $table->index('uuid');
            $table->index('user_id');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('auditable_uuid');
            $table->index('event');
            $table->index('created_at');
            $table->index('shop_id');
            // Listing queries filter by type and sort by recency together.
            $table->index(['auditable_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('audit')->dropIfExists('audit_logs');
    }
};
