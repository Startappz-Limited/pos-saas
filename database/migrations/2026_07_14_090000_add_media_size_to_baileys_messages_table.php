<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baileys_messages', function (Blueprint $table) {
            // Bytes actually written to disk. Lets us report per-shop usage and
            // enforce a quota without walking the filesystem.
            $table->unsignedBigInteger('media_size')->nullable()->after('media_filename');

            // Supports the retention sweep: "media older than N days".
            $table->index(['shop_id', 'created_at'], 'baileys_messages_shop_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('baileys_messages', function (Blueprint $table) {
            $table->dropIndex('baileys_messages_shop_created_index');
            $table->dropColumn('media_size');
        });
    }
};
