<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('platform'); // SocialPlatform enum value
            $table->string('account_name');
            $table->string('external_account_id')->nullable(); // page id / IG business id / ad account id
            $table->string('username')->nullable();
            $table->string('avatar_url')->nullable();

            // Encrypted credentials (access tokens, refresh tokens, secrets)
            $table->json('credentials')->nullable();

            // Free-form metadata returned by platform (page categories, ad-account currency, etc.)
            $table->json('metadata')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();

            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['shop_id', 'platform', 'external_account_id']);
            $table->index(['shop_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
