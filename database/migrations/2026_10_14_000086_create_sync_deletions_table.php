<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What offline tills must remove on their next pull (decision 4.5): one row
 * per deleted record, soft or hard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid')->nullable();
            $table->timestamp('deleted_at');
            $table->index(['business_id', 'deleted_at']);
            $table->index(['entity', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_deletions');
    }
};
