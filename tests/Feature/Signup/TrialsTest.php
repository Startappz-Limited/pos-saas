<?php

use App\Actions\Billing\ApplyPlatformPayment;
use App\Actions\Billing\ChangeTrialPlan;
use App\Actions\Billing\RecordManualPayment;
use App\Actions\Billing\SweepSubscriptions;
use App\Exceptions\PaymentRejected;
use App\Exceptions\PlanLimitExceeded;
use App\Exceptions\SubscriptionInactive;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\BusinessMembership;
use App\Models\Plan;
use App\Models\PlatformPayment;
use App\Models\Shop;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionReminder;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Trials from signup (Phase 6, section 2): every signup starts a 14-day
 * trial, either the Free Trial or a trial of the tier picked; choosing a tier
 * never moves the trial's end; paying during a trial starts the paid period
 * at that end, once.
 */
beforeEach(function () {
    ownerRole();
    $this->tenant = app(TenantContext::class);
});

function signup(array $overrides = []): array
{
    $form = [
        'name' => 'Wanjiku Kamau',
        'business_name' => 'Mama Mboga Stores',
        'email' => 'owner'.uniqid().'@mamamboga.co.ke',
        'password' => 'purple tractors drink warm milk',
        'password_confirmation' => 'purple tractors drink warm milk',
        ...$overrides,
    ];
    test()->post('/register', $form)->assertSessionHasNoErrors();

    $user = User::where('email', $form['email'])->firstOrFail();
    $business = Business::findOrFail($user->default_business_id);

    return [$user, $business];
}

function subscriptionOf(Business $business): Subscription
{
    return app(TenantContext::class)->run($business, fn () => Subscription::query()->with('plan')->firstOrFail());
}

it('starts a Free Trial by default: one shop, three staff, 14 days from signup', function () {
    $this->freezeTime();
    [$user, $business] = signup();
    $subscription = subscriptionOf($business);

    expect($subscription->plan->key)->toBe('free-trial')
        ->and($subscription->status)->toBe(Subscription::TRIALING)
        ->and($subscription->shop_count)->toBe(1)
        ->and($subscription->trial_ends_at->toDateTimeString())->toBe(now()->addDays(14)->toDateTimeString())
        ->and($this->tenant->run($business, fn () => Shop::query()->count()))->toBe(1)
        ->and($this->tenant->run($business, fn () => $user->fresh()->isBusinessOwner()))->toBeTrue();
});

it('starts a trial of the tier picked, with its shop count', function () {
    [, $business] = signup(['plan' => 'tier-2', 'shop_count' => 3]);
    $subscription = subscriptionOf($business);

    expect($subscription->plan->key)->toBe('tier-2')
        ->and($subscription->shop_count)->toBe(3)
        ->and((float) $subscription->price_at_signup)->toBe(3000.0)
        ->and($subscription->status)->toBe(Subscription::TRIALING);
});

it('does not take a trial from an unknown plan', function () {
    $this->post('/register', [
        'name' => 'X Person', 'business_name' => 'Shop', 'email' => 'x'.uniqid().'@mamamboga.co.ke',
        'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk',
        'plan' => 'enterprise',
    ])->assertSessionHasErrors('plan');
});

it('shows the plan picker with the plans from plan data', function () {
    $this->get('/register')->assertOk()
        ->assertSee('Free Trial')->assertSee('Tier 1')->assertSee('Tier 2')->assertSee('Tier 3')
        ->assertSee('14-day trial, no payment now');
});

it('will not start a second Free Trial for the same email, however it is spelled', function () {
    signup(['email' => 'owner.one@gmail.com']);
    auth()->logout();

    $this->post('/register', [
        'name' => 'Again', 'business_name' => 'Another', 'email' => 'ownerone+2@gmail.com',
        'password' => 'purple tractors drink warm milk', 'password_confirmation' => 'purple tractors drink warm milk',
    ])->assertSessionHasErrors('email');
});

it('stops a Free Trial business adding a second shop or a fourth staff member, invites included', function () {
    [$owner, $business] = signup();
    [$a, $b] = [User::factory()->create(), User::factory()->create()];

    $this->tenant->run($business, function () use ($business, $a, $b): void {
        expect(fn () => Shop::create(['business_id' => $business->id, 'name' => 'Second']))->toThrow(PlanLimitExceeded::class);

        // The owner is one of three; one more fits, and a pending invite holds the last seat.
        $a->joinBusiness($business);
        BusinessInvite::create([
            'business_id' => $business->id, 'email' => 'pending@mamamboga.co.ke', 'role_id' => 1,
            'token_hash' => 'x', 'expires_at' => now()->addDay(),
        ]);

        expect(fn () => $b->joinBusiness($business))->toThrow(PlanLimitExceeded::class);
    });
});

it('lets an expired invite stop holding a seat', function () {
    [, $business] = signup();
    [$a, $b] = [User::factory()->create(), User::factory()->create()];

    $this->tenant->run($business, function () use ($business, $a, $b): void {
        BusinessInvite::create([
            'business_id' => $business->id, 'email' => 'old@mamamboga.co.ke', 'role_id' => 1,
            'token_hash' => 'y', 'expires_at' => now()->subMinute(),
        ]);
        $a->joinBusiness($business);
        $b->joinBusiness($business);

        expect(BusinessMembership::query()->where('business_id', $business->id)->where('status', 'active')->count())->toBe(3);
    });
});

it('switches tier during a trial at once and never moves the trial end', function () {
    [$owner, $business] = signup();
    $ends = subscriptionOf($business)->trial_ends_at;

    $this->travel(5)->days();
    $this->actingAs($owner)->post(route('billing.plan'), ['plan' => 'tier-2', 'shop_count' => 3])->assertSessionHas('success');

    $subscription = subscriptionOf($business);
    expect($subscription->plan->key)->toBe('tier-2')
        ->and($subscription->shop_count)->toBe(3)
        ->and($subscription->status)->toBe(Subscription::TRIALING)
        ->and($subscription->trial_ends_at)->toEqual($ends);

    foreach (['tier-3', 'tier-1', 'tier-2'] as $key) {
        $this->travel(1)->day();
        $this->actingAs($owner)->post(route('billing.plan'), ['plan' => $key, 'shop_count' => 1]);
        expect(subscriptionOf($business)->trial_ends_at)->toEqual($ends);
    }
});

it('audits each plan change during a trial', function () {
    migrateAuditDatabase();
    [$owner, $business] = signup();

    $this->actingAs($owner)->post(route('billing.plan'), ['plan' => 'tier-3', 'shop_count' => 1]);

    $row = AuditLog::query()->where('auditable_type', Subscription::class)->where('event', 'updated')->latest('id')->first();
    expect($row)->not->toBeNull()
        ->and($row->user_id)->toBe($owner->id)
        ->and(json_encode($row->new_values))->toContain('plan_id');
});

it('warns before a smaller plan puts the business over its limits, and changes nothing until confirmed', function () {
    [$owner, $business] = signup(['plan' => 'tier-3', 'shop_count' => 3]);
    $this->tenant->run($business, function () use ($business): void {
        Shop::create(['business_id' => $business->id, 'name' => 'Branch 2']);
        Shop::create(['business_id' => $business->id, 'name' => 'Branch 3']);
    });

    $this->actingAs($owner)->post(route('billing.plan'), ['plan' => 'tier-1', 'shop_count' => 1])
        ->assertSessionHas('plan_warnings');
    expect(subscriptionOf($business)->plan->key)->toBe('tier-3');

    $this->actingAs($owner)->post(route('billing.plan'), ['plan' => 'tier-1', 'shop_count' => 1, 'confirm' => 1])
        ->assertSessionHas('success');

    $this->tenant->run($business, function (): void {
        $entitlements = app(Entitlements::class);
        expect($entitlements->isOverShopQuota())->toBeTrue()
            ->and($entitlements->readOnlyReason())->toBe('shops_over_quota');
    });
});

it('keeps existing staff over a smaller plan\'s limit and refuses new ones', function () {
    [$owner, $business] = signup(['plan' => 'tier-3']);
    $staff = User::factory()->count(4)->create();
    $extra = User::factory()->create();
    $this->tenant->run($business, fn () => $staff->each->joinBusiness($business));

    $change = app(ChangeTrialPlan::class);
    $subscription = subscriptionOf($business);
    $tier1 = Plan::where('key', 'tier-1')->first();
    expect($this->tenant->run($business, fn () => $change->warnings($subscription, $tier1, 1)))->not->toBeEmpty();

    $this->tenant->run($business, function () use ($change, $tier1, $business, $extra): void {
        $change->execute($tier1, 1);
        expect(BusinessMembership::query()->where('business_id', $business->id)->where('status', 'active')->count())->toBe(5)
            ->and(fn () => $extra->joinBusiness($business))->toThrow(PlanLimitExceeded::class);
    });
});

it('refuses a payment while on the Free Trial', function () {
    [, $business] = signup();

    expect(fn () => app(RecordManualPayment::class)->execute($business, PlatformPayment::SUBSCRIPTION, 3000))
        ->toThrow(PaymentRejected::class);
});

it('starts the paid period at the trial end when paid early, and applies a payment once', function () {
    [$owner, $business] = signup(['plan' => 'tier-1', 'shop_count' => 1]);
    $ends = subscriptionOf($business)->trial_ends_at;

    $this->travel(5)->days();
    $payment = app(RecordManualPayment::class)->execute($business, PlatformPayment::SUBSCRIPTION, 1500, 'QWE123ABCD');

    $subscription = subscriptionOf($business);
    expect($subscription->status)->toBe(Subscription::ACTIVE)
        ->and($subscription->trial_ends_at)->toEqual($ends)
        ->and($subscription->current_period_end->toDateString())->toBe($ends->copy()->addMonthNoOverflow()->toDateString());

    // The same payment applied again changes nothing.
    expect($this->tenant->run($business, fn () => app(ApplyPlatformPayment::class)->execute($payment)))->toBeFalse()
        ->and(subscriptionOf($business)->current_period_end)->toEqual($subscription->current_period_end);
});

it('lapses at the trial end into the read-only state', function () {
    [, $business] = signup();

    $this->travel(15)->days();

    $this->tenant->run($business, function (): void {
        $entitlements = app(Entitlements::class);
        expect($entitlements->isLapsed())->toBeTrue()
            ->and($entitlements->readOnlyReason())->toBe(SubscriptionInactive::LAPSED);
    });
});

it('reminds a trial three days and one day before it ends, once each', function () {
    Notification::fake();
    [, $business] = signup(['plan' => 'tier-1']);

    $this->travelTo(subscriptionOf($business)->trial_ends_at->copy()->subDays(3)->addHour());
    app(SweepSubscriptions::class)->execute();
    app(SweepSubscriptions::class)->execute();
    Notification::assertSentTimes(SubscriptionReminder::class, 1);

    $this->travelTo(subscriptionOf($business)->trial_ends_at->copy()->subHours(12));
    app(SweepSubscriptions::class)->execute();
    app(SweepSubscriptions::class)->execute();
    Notification::assertSentTimes(SubscriptionReminder::class, 2);
});

it('carries users and roles over when a trial becomes a paid plan', function () {
    [$owner, $business] = signup(['plan' => 'tier-2']);
    $member = User::factory()->create();
    $this->tenant->run($business, fn () => $member->joinBusiness($business));

    app(RecordManualPayment::class)->execute($business, PlatformPayment::SUBSCRIPTION, 3000);

    $this->tenant->run($business, function () use ($owner, $business): void {
        expect(BusinessMembership::query()->where('business_id', $business->id)->where('status', 'active')->count())->toBe(2)
            ->and($owner->fresh()->isBusinessOwner())->toBeTrue();
    });
});
