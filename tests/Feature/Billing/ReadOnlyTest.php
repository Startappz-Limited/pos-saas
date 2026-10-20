<?php

use App\Actions\Billing\SweepSubscriptions;
use App\Exceptions\PlanLimitExceeded;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\Register;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminder;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * Plan limits and read-only mode (Phase 4, decisions 4.6 and 4.9): adding past
 * the plan is refused everywhere; a lapsed or over-quota business can read and
 * export but every other write is refused with `subscription_inactive`.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake('tenant');
    Storage::fake('public');
    Notification::fake();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->tenant = app(TenantContext::class);
});

function settingsPayload(): array
{
    return ['name' => 'Renamed', 'currency' => 'KES', 'timezone' => 'Africa/Nairobi'];
}

it('refuses a shop beyond the shops the subscription pays for, on the model, the web and the API', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 2]);

    expect(fn () => $this->tenant->run($this->a->business, fn () => Shop::factory()->create(['business_id' => $this->a->business->id, 'manager_id' => null])))->toThrow(PlanLimitExceeded::class);

    $payload = ['name' => 'Third', 'code' => 'THIRD', 'status' => 'active'];
    $this->actingAs($this->a->owner)->postJson('/api/shops', $payload)->assertStatus(402)->assertJsonPath('code', 'plan_limit_reached')->assertJsonPath('limit', 'shops');
    $this->actingAs($this->a->owner)->post(route('shops.store'), $payload)->assertSessionHas('error');

    // Business B has no subscription and is unaffected.
    expect($this->tenant->run($this->b->business, fn () => Shop::factory()->create(['business_id' => $this->b->business->id, 'manager_id' => null]))->exists)->toBeTrue();
});

it('refuses a register beyond the plan, per shop', function () {
    subscribe($this->a->business, 'tier-1', ['shop_count' => 5]);
    $shop = $this->tenant->run($this->a->business, fn () => Shop::factory()->create(['business_id' => $this->a->business->id, 'manager_id' => null]));

    // The shop's default register is the one the plan allows.
    expect($this->tenant->run($this->a->business, fn () => Register::query()->where('shop_id', $shop->id)->count()))->toBe(1)
        ->and(fn () => $this->tenant->run($this->a->business, fn () => Register::factory()->create(['shop_id' => $shop->id])))
        ->toThrow(PlanLimitExceeded::class);
});

it('refuses staff past the plan', function () {
    subscribe($this->a->business, 'tier-1', ['shop_count' => 5]);

    // Tier 1 allows 3 staff: the owner, the cashier and the user in both businesses fill it.
    expect(fn () => User::factory()->memberOf($this->a->business)->create())->toThrow(PlanLimitExceeded::class);
});

it('is read-only after the subscription lapses, for reading and exporting still open', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'status' => Subscription::ACTIVE, 'current_period_end' => now()->subDays(2)]);

    $this->actingAs($this->a->owner)->putJson(route('business.settings.update'), settingsPayload())
        ->assertStatus(402)->assertJsonPath('code', 'subscription_inactive')->assertJsonPath('reason', 'subscription_inactive');
    expect($this->a->business->fresh()->name)->not->toBe('Renamed');

    $this->actingAs($this->a->owner)->get(route('dashboard'))->assertOk();
    $this->actingAs($this->a->owner)->get(route('customers.index'))->assertOk();
    $this->actingAs($this->a->owner)->post(route('business.exports.store'))->assertRedirect(route('business.data'));

    // The web shows the reason too.
    $this->actingAs($this->a->owner)->from('/x')->put(route('business.settings.update'), settingsPayload())->assertSessionHas('error');
});

it('refuses every API write when lapsed, flat and v1', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'status' => Subscription::ACTIVE, 'current_period_end' => now()->subDay()]);

    $this->actingAs($this->a->owner)->postJson('/api/customers', ['name' => 'X', 'customer_type' => 'retail'])->assertStatus(402)->assertJsonPath('code', 'subscription_inactive');
    $this->actingAs($this->a->owner)->getJson('/api/customers')->assertOk();
});

it('lets the owner delete shops while over the quota, and nothing else', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 1]);

    expect($this->tenant->run($this->a->business, fn () => app(Entitlements::class)->readOnlyReason()))->toBe('shops_over_quota');

    $this->actingAs($this->a->owner)->putJson(route('business.settings.update'), settingsPayload())->assertStatus(402)->assertJsonPath('reason', 'shops_over_quota');

    $this->actingAs($this->a->owner)->delete(route('shops.destroy', $this->a->shops[1]))->assertRedirect();

    $this->actingAs($this->a->owner)->putJson(route('business.settings.update'), settingsPayload())->assertRedirect(route('business.settings.edit'));
});

it('is read-only for background work while lapsed', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'status' => Subscription::ACTIVE, 'current_period_end' => now()->subDay()]);

    expect($this->a->business->fresh()->runsBackgroundWork())->toBeFalse()
        ->and($this->b->business->fresh()->runsBackgroundWork())->toBeTrue();
});

it('reminds the owners before a period ends, once', function () {
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'status' => Subscription::ACTIVE, 'current_period_end' => now()->addDays(2)]);

    expect(app(SweepSubscriptions::class)->execute()['reminded'])->toBe(1)
        ->and(app(SweepSubscriptions::class)->execute()['reminded'])->toBe(0);

    Notification::assertSentTo($this->a->owner, SubscriptionReminder::class, fn ($n) => $n->kind === SubscriptionReminder::RENEWAL);
    Notification::assertNotSentTo($this->b->owner, SubscriptionReminder::class);
});

it('warns before the grace period ends and starts the closure on the 31st business day', function () {
    config(['billing.holidays' => []]);
    Bus::fake();
    Carbon::setTestNow('2026-11-03 09:00');
    subscribe($this->a->business, 'tier-3', ['shop_count' => 5, 'status' => Subscription::ACTIVE, 'current_period_end' => Carbon::parse('2026-11-02 08:00')]);
    // 30 business days after Monday 2 November is Monday 14 December.

    Carbon::setTestNow('2026-11-30 09:00');
    expect(app(SweepSubscriptions::class)->execute())->toBe(['reminded' => 0, 'warned' => 0, 'closed' => 0]);

    Carbon::setTestNow('2026-12-08 09:00'); // five business days before the 14th
    expect(app(SweepSubscriptions::class)->execute())->toBe(['reminded' => 0, 'warned' => 1, 'closed' => 0]);
    expect(app(SweepSubscriptions::class)->execute()['warned'])->toBe(0);
    Notification::assertSentTo($this->a->owner, SubscriptionReminder::class, fn ($n) => $n->kind === SubscriptionReminder::CLOSURE);

    Carbon::setTestNow('2026-12-14 12:00'); // the 30th business day: still read-only
    expect(app(SweepSubscriptions::class)->execute()['closed'])->toBe(0);

    Carbon::setTestNow('2026-12-15 09:00'); // the 31st
    expect(app(SweepSubscriptions::class)->execute()['closed'])->toBe(1);

    expect($this->a->business->fresh()->status)->toBe(Business::STATUS_CLOSING)
        ->and(BusinessClosure::query()->where('business_id', $this->a->business->id)->value('reason'))->toBe(BusinessClosure::REASON_LAPSED);

    Carbon::setTestNow();
});

it('keeps a route behind the plan\'s feature', function () {
    subscribe($this->a->business, 'tier-1', ['shop_count' => 5]);
    subscribe($this->b->business, 'tier-3', ['shop_count' => 5]);

    $this->actingAs($this->a->owner)->getJson(route('campaigns.index'))->assertStatus(402)->assertJsonPath('code', 'feature_unavailable')->assertJsonPath('feature', 'marketing');
    $this->actingAs($this->a->owner)->getJson(route('baileys.sessions.index'))->assertStatus(402)->assertJsonPath('feature', 'whatsapp');
    $this->actingAs($this->b->owner)->get(route('campaigns.index'))->assertOk();
});
