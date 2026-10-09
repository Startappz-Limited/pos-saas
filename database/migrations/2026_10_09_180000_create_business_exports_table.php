<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A download of everything a business holds (BusinessExporter). The owner
     * must download one before they can close the business, and closing it
     * builds a final one from the frozen data.
     */
    public function up(): void
    {
        Schema::create('business_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->boolean('is_final')->default(false);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->timestamp('closing_requested_at')->nullable()->after('owner_id');
            $table->timestamp('purge_after')->nullable()->after('closing_requested_at');
            $table->foreignId('closing_requested_by')->nullable()->after('purge_after')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closing_requested_by');
            $table->dropColumn(['closing_requested_at', 'purge_after']);
        });

        Schema::dropIfExists('business_exports');
    }
};
