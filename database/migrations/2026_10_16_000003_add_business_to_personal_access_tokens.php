<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `/api/v1` tokens are bound to one business (decision 4.4, stage 3); a token
 * with no business is a flat API token, which acts on the user's default
 * business (4.8). Closing a business removes its tokens.
 *
 * Additive: existing tokens keep working as flat API tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('tokenable_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
