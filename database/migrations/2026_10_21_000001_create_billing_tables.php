<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Billing, entitlements and AI metering (Phase 4, decision 4.6).
 *
 * - `plans`: platform data, editable without a deploy. Tier 1, 2 and 3 at
 *   KES 1,500, 3,000 and 5,000 per shop per month; the limits, features and
 *   trial length are placeholders (null limit = unlimited).
 * - `subscriptions`: one per business: the plan, the shops it pays for, the
 *   price when it signed up, its status and dates.
 * - `platform_payments`: money received by the platform (subscription or AI
 *   top-up), by hand or through the M-Pesa API.
 * - `ai_credit_balances`, `ai_usage_events`, `ai_blocked_work`: the prepaid
 *   balance, every metered call, and work waiting for credit.
 *
 * Carries existing data across: every business that already exists gets a
 * trial on the top tier, so nobody is locked out when billing arrives.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 30)->unique();
            $table->string('name');
            $table->decimal('price_per_shop', 10, 2);
            $table->char('currency', 3)->default('KES');
            $table->json('limits')->nullable()->comment('staff, products, registers (per shop), whatsapp_sessions; null = unlimited');
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->unsignedInteger('shop_count')->default(1)->comment('Shops paid for');
            $table->decimal('price_at_signup', 10, 2);
            $table->string('status', 20)->default('trialing')->comment('trialing, active or cancelled');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('renewal_reminded_at')->nullable()->comment('The reminder before this trial or period ends');
            $table->timestamp('lapse_warned_at')->nullable()->comment('The warning before the closure starts');
            $table->timestamps();
        });

        Schema::create('platform_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20)->comment('subscription or ai_topup');
            $table->decimal('amount', 10, 2);
            $table->char('currency', 3)->default('KES');
            $table->string('method', 20)->default('manual')->comment('manual or mpesa');
            $table->string('status', 20)->default('pending')->comment('pending, confirmed or failed');
            $table->string('mpesa_receipt')->nullable()->unique();
            $table->string('checkout_request_id')->nullable()->unique()->comment('Our reference with M-Pesa: payments are matched on it, never on a phone number');
            $table->string('phone', 20)->nullable();
            $table->json('raw')->nullable()->comment('What the M-Pesa API returned');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('applied_at')->nullable()->comment('Set once, when the payment was applied: makes applying idempotent');
            $table->timestamps();

            $table->index(['business_id', 'purpose', 'status']);
        });

        Schema::create('ai_credit_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 4)->default(0);
            $table->timestamp('exhausted_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->decimal('credits', 14, 4)->default(0);
            $table->string('kind', 20)->default('call')->comment('call, topup or refund');
            $table->string('status', 20)->default('settled')->comment('settled, failed or refused');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });

        Schema::create('ai_blocked_work', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40)->comment('campaign_content');
            $table->json('payload');
            $table->string('status', 20)->default('waiting')->comment('waiting, done or failed');
            $table->json('result')->nullable();
            $table->string('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });

        $this->seedPlans();
        $this->startTrialsForExistingBusinesses();
    }

    public function down(): void
    {
        foreach (['ai_blocked_work', 'ai_usage_events', 'ai_credit_balances', 'platform_payments', 'subscriptions', 'plans'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function seedPlans(): void
    {
        $now = now();
        $plans = [
            ['key' => 'tier-1', 'name' => 'Tier 1', 'price' => 1500, 'limits' => ['staff' => 3, 'products' => 500, 'registers' => 1, 'whatsapp_sessions' => 1],
                'features' => ['pos', 'inventory', 'reports']],
            ['key' => 'tier-2', 'name' => 'Tier 2', 'price' => 3000, 'limits' => ['staff' => 10, 'products' => 5000, 'registers' => 3, 'whatsapp_sessions' => 2],
                'features' => ['pos', 'inventory', 'reports', 'ecommerce_sync', 'whatsapp']],
            ['key' => 'tier-3', 'name' => 'Tier 3', 'price' => 5000, 'limits' => ['staff' => null, 'products' => null, 'registers' => null, 'whatsapp_sessions' => 5],
                'features' => ['pos', 'inventory', 'reports', 'ecommerce_sync', 'whatsapp', 'ai', 'marketing', 'api']],
        ];

        foreach ($plans as $index => $plan) {
            DB::table('plans')->insert([
                'uuid' => (string) Str::uuid(),
                'key' => $plan['key'],
                'name' => $plan['name'],
                'price_per_shop' => $plan['price'],
                'currency' => 'KES',
                'limits' => json_encode($plan['limits']),
                'features' => json_encode($plan['features']),
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Existing businesses (the imported one, UAT's) start a trial on the top
     * tier now, paying for the shops they have, so none is read-only the
     * moment this runs.
     */
    private function startTrialsForExistingBusinesses(): void
    {
        $plan = DB::table('plans')->where('key', 'tier-3')->first();
        $now = now();

        foreach (DB::table('businesses')->where('status', 'active')->get(['id']) as $business) {
            $shops = DB::table('shops')->where('business_id', $business->id)->whereNull('deleted_at')->count();

            DB::table('subscriptions')->insert([
                'uuid' => (string) Str::uuid(),
                'business_id' => $business->id,
                'plan_id' => $plan->id,
                'shop_count' => max(1, $shops),
                'price_at_signup' => $plan->price_per_shop,
                'status' => 'trialing',
                'trial_ends_at' => $now->copy()->addDays($plan->trial_days),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
