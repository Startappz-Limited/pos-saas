<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, ensure we have a default sale source for existing records
        $defaultSource = DB::table('sale_sources')->where('name', 'Walk-in')->first();

        if (! $defaultSource) {
            $defaultSourceId = DB::table('sale_sources')->insertGetId([
                'name' => 'Walk-in',
                'description' => 'Direct walk-in customer (default for old records)',
                'icon' => 'solar:shop-bold-duotone',
                'color' => '#6b7280',
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $defaultSourceId = $defaultSource->id;
        }

        Schema::table('sales', function (Blueprint $table) {
            // Sale source (will be mandatory after data migration)
            $table->foreignId('source_id')->nullable()->after('customer_id')->constrained('sale_sources');

            // Delivery location (nullable to allow old records)
            $table->text('delivery_location')->nullable()->after('source_id');

            // Walk-in customer details (for non-registered customers)
            $table->string('walk_in_customer_name')->nullable()->after('delivery_location');
            $table->string('walk_in_customer_email')->nullable()->after('walk_in_customer_name');
            $table->string('walk_in_customer_phone')->nullable()->after('walk_in_customer_email');

            // Delivery company/rider (for completed sales)
            $table->foreignId('delivery_company_id')->nullable()->after('walk_in_customer_phone')->constrained('delivery_companies');
        });

        // Update existing sales records with the default source
        DB::table('sales')
            ->whereNull('source_id')
            ->update([
                'source_id' => $defaultSourceId,
                'delivery_location' => 'Not specified',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropColumn('source_id');
            $table->dropColumn('delivery_location');
            $table->dropColumn('walk_in_customer_name');
            $table->dropColumn('walk_in_customer_email');
            $table->dropColumn('walk_in_customer_phone');
            $table->dropForeign(['delivery_company_id']);
            $table->dropColumn('delivery_company_id');
        });
    }
};
