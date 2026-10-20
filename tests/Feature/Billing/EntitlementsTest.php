<?php

use App\Actions\Business\RegisterBusiness;
use App\Actions\Business\StartTrial;
use App\Enums\Feature;
use App\Enums\Limit;
use App\Exceptions\PlanLimitExceeded;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Services\Billing\BusinessDays;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;

/**
 * Plans, the trial and the one entitlements check (Phase 4, decision 4.6).
 */
it('seeds the three tiers at 1,500, 3,000 and 5,000 per shop', function () {
    expect(Plan::query()->orderBy('sort_order')->pluck('price_per_shop', 'key')->map(fn ($price) => (float) $price)->all())
        ->toBe(['tier-1' => 1500.0, 'tier-2' => 3000.0, 'tier-3' => 5000.0]);
});

it('treats a business with no subscription as unmetered', function () {
    $entitlements = app(Entitlements::class);

    expect($entitlements->isMetered())->toBeFalse()
        ->and($entitlements->hasFeature(Feature::Ai))->toBeTrue()
        ->and($entitlements->limit(Limit::Staff))->toBeNull()
        ->and($entitlements->isReadOnly())->toBeFalse();
});

it('answers features and limits from the plan', function () {
    $business = testBusiness();
    subscribe($business, 'tier-1', ['shop_count' => 3]);

    $this->app->forgetInstance(Entitlements::class);
    $entitlements = app(Entitlements::class);

    expect($entitlements->hasFeature(Feature::Pos))->toBeTrue()
        ->and($entitlements->hasFeature(Feature::Ai))->toBeFalse()
        ->and($entitlements->limit(Limit::Staff))->toBe(3)
        ->and($entitlements->limit(Limit::Shops))->toBe(3);

    $entitlements->assertCanAdd(Limit::Staff, 2);
    expect(fn () => $entitlements->assertCanAdd(Limit::Staff, 3))->toThrow(PlanLimitExceeded::class);
});

it('has no cap where the plan leaves a limit null', function () {
    subscribe(testBusiness(), 'tier-3');

    app(Entitlements::class)->assertCanAdd(Limit::Staff, 10_000);

    expect(app(Entitlements::class)->limit(Limit::Staff))->toBeNull();
});

it('judges standing from the dates: a trial that ended is lapsed from that moment', function () {
    $business = testBusiness();
    subscribe($business, 'tier-3', ['status' => Subscription::TRIALING, 'trial_ends_at' => now()->subDays(3), 'current_period_end' => null]);

    $entitlements = app(Entitlements::class);

    expect($entitlements->isLapsed())->toBeTrue()
        ->and($entitlements->isReadOnly())->toBeTrue()
        ->and($entitlements->lapsedAt()->isSameDay(now()->subDays(3)))->toBeTrue();
});

it('is read-only while the business has more shops than it pays for', function () {
    $business = testBusiness();
    app(TenantContext::class)->run($business, fn () => Shop::factory()->count(2)->create(['manager_id' => null]));
    subscribe($business, 'tier-3', ['shop_count' => 1]);

    expect(app(Entitlements::class)->readOnlyReason())->toBe('shops_over_quota');
});

it('counts business days, skipping weekends and holidays', function () {
    config(['billing.holidays' => ['2026-12-25']]);
    $days = new BusinessDays;

    // Friday 18 December + 5 business days: 21, 22, 23, 24 and then 28 (25 is a holiday, 26-27 a weekend).
    expect($days->add(Carbon::parse('2026-12-18'), 5)->toDateString())->toBe('2026-12-28')
        ->and($days->between(Carbon::parse('2026-12-18'), Carbon::parse('2026-12-28')))->toBe(5)
        ->and($days->isBusinessDay(Carbon::parse('2026-12-25')))->toBeFalse()
        ->and($days->isBusinessDay(Carbon::parse('2026-12-24')))->toBeTrue();
});

it('adds the configured number of business days for the grace end', function () {
    config(['billing.holidays' => []]);
    $business = testBusiness();
    subscribe($business, 'tier-1', ['status' => Subscription::ACTIVE, 'current_period_end' => Carbon::parse('2026-11-02 08:00')]);
    Carbon::setTestNow('2026-11-03');

    // 30 business days after Monday 2 November is Monday 14 December.
    expect(app(Entitlements::class)->graceEndsAt()->toDateString())->toBe('2026-12-14');

    Carbon::setTestNow();
});

it('starts a trial on the top tier when the owner verifies their email', function () {
    seedRolesAndPermissions();
    $user = app(RegisterBusiness::class)->execute([
        'name' => 'Owner', 'email' => 'owner@trial.test', 'password' => 'Str0ng-P0S-Pass!', 'business_name' => 'Trial Co',
    ]);
    $business = $user->memberships()->first()->business;

    expect(Subscription::query()->withoutGlobalScopes()->where('business_id', $business->id)->exists())->toBeFalse();

    event(new Verified($user));

    $subscription = Subscription::query()->withoutGlobalScopes()->where('business_id', $business->id)->with('plan')->first();
    expect($subscription->plan->key)->toBe('tier-3')
        ->and($subscription->status)->toBe(Subscription::TRIALING)
        ->and($subscription->isInGoodStanding())->toBeTrue()
        ->and($subscription->trial_ends_at->isSameDay(now()->addDays($subscription->plan->trial_days)))->toBeTrue()
        ->and($subscription->shop_count)->toBe(1);
});

it('starts a trial only once per business', function () {
    $business = Business::factory()->create();

    $first = app(StartTrial::class)->execute($business);
    $second = app(StartTrial::class)->execute($business);

    expect($second->id)->toBe($first->id)
        ->and(Subscription::query()->withoutGlobalScopes()->where('business_id', $business->id)->count())->toBe(1);
});
