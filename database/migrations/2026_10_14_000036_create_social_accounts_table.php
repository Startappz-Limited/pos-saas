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
            $table->uuid('uuid');
            $table->unsignedBigInteger('shop_id');
            $table->string('platform');
            $table->string('account_name');
            $table->string('external_account_id')->nullable();
            $table->string('username')->nullable();
            $table->string('avatar_url')->nullable();
            $table->text('credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->unsignedBigInteger('connected_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['shop_id', 'platform', 'external_account_id']);
            $table->index(['shop_id', 'platform']);
            $table->unique('uuid');
            $table->foreign('connected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('shop_id')->references('id')->on('shops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
