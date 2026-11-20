<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 11c, step 2: low-stock alerts hold base-unit quantities (decimal, like the stock they describe), and each
 * records whether a person or the system raised it, and how it was resolved. Additive: the existing values carry
 * across unchanged (decimal(15,3) holds every whole number), and the existing rows are system alerts, as stage 11c
 * decides (none is open on UAT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('low_stock_alerts', function (Blueprint $table) {
            $table->decimal('current_quantity', 15, 3)->change();
            $table->decimal('threshold_quantity', 15, 3)->change();
            $table->string('source', 20)->default('user')->after('status');
            $table->string('resolution', 40)->nullable()->after('acknowledged_by');
            $table->timestamp('resolved_at')->nullable()->after('resolution');
        });

        DB::table('low_stock_alerts')->update(['source' => 'system']);
    }

    public function down(): void
    {
        Schema::table('low_stock_alerts', function (Blueprint $table) {
            $table->dropColumn(['source', 'resolution', 'resolved_at']);
        });
    }
};
