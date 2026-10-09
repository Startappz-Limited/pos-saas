<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('baileys_chats', 'phone')) {
            Schema::table('baileys_chats', function (Blueprint $table) {
                $table->string('phone', 32)->nullable()->after('jid');
            });
        }

        if (! Schema::hasIndex('baileys_chats', ['shop_id', 'phone'])) {
            Schema::table('baileys_chats', function (Blueprint $table) {
                $table->index(['shop_id', 'phone']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('baileys_chats', ['shop_id', 'phone'])) {
            Schema::table('baileys_chats', function (Blueprint $table) {
                $table->dropIndex(['shop_id', 'phone']);
            });
        }

        if (Schema::hasColumn('baileys_chats', 'phone')) {
            Schema::table('baileys_chats', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
