<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Social/AI marketing extensions
            $table->boolean('auto_post_enabled')->default(false)->after('promo_code');
            $table->boolean('ai_assist_enabled')->default(false)->after('auto_post_enabled');
            $table->json('ai_settings')->nullable()->after('ai_assist_enabled');
            $table->json('content_template')->nullable()->after('ai_settings');
            $table->string('default_landing_url')->nullable()->after('content_template');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'auto_post_enabled',
                'ai_assist_enabled',
                'ai_settings',
                'content_template',
                'default_landing_url',
            ]);
        });
    }
};
