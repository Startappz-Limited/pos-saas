<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a membership was removed (stage 10a, step 1), distinct from
 * `updated_at`, which any later change to the row also moves. Null for every
 * existing row; `members:clean-removed --apply` backfills the ones that are
 * already removed in substance (inactive, no role) from `updated_at`, the
 * best date there is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('business_user', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
