<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Phase 6: the Free Trial as plan data (not code): one shop, three staff, the
 * trial's own limits, 14 days. `is_trial_plan` keeps it out of the lists of
 * plans a business can pay for. The tiers' trial length is set to 14 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_trial_plan')->default(false)->after('is_active')
                ->comment('The Free Trial: offered at signup, never paid for');
        });

        DB::table('plans')->where('key', '!=', 'free-trial')->update(['trial_days' => 14]);

        if (! DB::table('plans')->where('key', 'free-trial')->exists()) {
            DB::table('plans')->insert([
                'uuid' => (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'plan:free-trial'),
                'key' => 'free-trial',
                'name' => 'Free Trial',
                'price_per_shop' => 0,
                'currency' => 'KES',
                'limits' => json_encode(['staff' => 3, 'products' => 100, 'registers' => 1, 'whatsapp_sessions' => 1]),
                'features' => json_encode(['pos', 'inventory', 'reports']),
                'trial_days' => 14,
                'is_active' => true,
                'is_trial_plan' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // A subscription may point at the Free Trial; refuse rather than orphan it.
        $plan = DB::table('plans')->where('key', 'free-trial')->first();

        if ($plan !== null && DB::table('subscriptions')->where('plan_id', $plan->id)->exists()) {
            throw new RuntimeException('Businesses are on the Free Trial; it cannot be removed.');
        }

        DB::table('plans')->where('key', 'free-trial')->delete();

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_trial_plan');
        });
    }
};
