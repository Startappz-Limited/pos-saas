<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit rows gain the business they belong to (stage 1c), so reads can be
 * limited to the current business. Nullable: rows written before this carry
 * none until `audit:backfill-business` fills them from their shop or record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('audit')->table('audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('business_id')->nullable()->after('shop_id');
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('audit')->table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropColumn('business_id');
        });
    }
};
