<?php

use App\Actions\Business\StartTrial;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Businesses in the platform console, Phase 5 (B1): the list and a business's
 * page with its figures; suspend and reactivate (suspension blocks the web,
 * both APIs and background work, and nothing else); change plan; extend
 * trial. Every action, reads included, is audited. (Owners get a 404 on these
 * routes: PlatformAccessTest walks every console route.)
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->operator = platformOperator();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
    $this->b = $this->fixture->b->business;
});

/**
 * The latest console audit row for $action.
 */
function consoleAudit(string $action): ?AuditLog
{
    return AuditLog::query()->latest('id')->get()->first(fn (AuditLog $row): bool => in_array($action, $row->tags ?? [], true));
}

/**
 * A `/api/v1` token for $user bound to $business.
 */
function v1TokenIn($user, Business $business): string
{
    app(TenantContext::class)->forget();
    $token = $user->createToken('till', ['*']);
    $token->accessToken->forceFill(['business_id' => $business->id])->save();

    return $token->plainTextToken;
}

it('lists every business with its figures, filters it, and records the read', function () {
    $businesses = $this->actingAs($this->operator)->get(route('platform.businesses.index'))->assertOk()
        ->assertSeeText('Business A')->assertSeeText('Business B')->viewData('businesses');

    // Counted in B directly (each shop has its default register).
    $registersOfB = DB::table('registers')->join('shops', 'shops.id', '=', 'registers.shop_id')->where('shops.business_id', $this->b->id)->count();
    $membersOf = fn (Business $business): int => DB::table('business_user')->where('business_id', $business->id)->where('status', 'active')->count();
    $b = $businesses->firstWhere('id', $this->b->id);
    expect((int) $b->shops_count)->toBe(2)
        ->and((int) $b->registers_count)->toBe($registersOfB)->toBeGreaterThan(0)
        ->and((int) $b->members_count)->toBe($membersOf($this->b))->toBeGreaterThanOrEqual(3)
        ->and((int) $b->products_count)->toBe(1)
        // Not business A's figures.
        ->and((int) $businesses->firstWhere('id', $this->fixture->a->business->id)->members_count)->toBe($membersOf($this->fixture->a->business));

    $this->actingAs($this->operator)->get(route('platform.businesses.index', ['search' => 'Business B']))->assertOk()
        ->assertSeeText('Business B')->assertDontSeeText('Business A');

    expect(consoleAudit('businesses.listed')->new_values)->toMatchArray(['search' => 'Business B'])
        ->and(consoleAudit('businesses.listed')->user_id)->toBe($this->operator->id);
});

it('shows a business with its members and their roles there, and records the read on that business', function () {
    $this->actingAs($this->operator)->get(route('platform.businesses.show', $this->b))->assertOk()
        ->assertSeeText('Bravo Owner')->assertSeeText('Bravo Cashier')->assertSeeText('Owner')->assertSeeText('Cashier')
        ->assertDontSeeText('Alpha Owner');

    $audit = consoleAudit('business.viewed');
    expect($audit->business_id)->toBe($this->b->id)->and($audit->user_id)->toBe($this->operator->id);
});

it('suspends a business only with a reason and the confirmation', function () {
    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['confirm' => '1'])->assertSessionHasErrors('reason');
    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['reason' => 'Unpaid'])->assertSessionHasErrors('confirm');

    expect($this->b->fresh()->status)->toBe(Business::STATUS_ACTIVE);
});

it('blocks the web, the flat API, /api/v1 and background work of a suspended business, and only that business', function () {
    $owner = $this->fixture->b->owner;
    $v1 = v1TokenIn($owner, $this->b);

    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['reason' => 'Fraud check', 'confirm' => '1'])
        ->assertRedirect(route('platform.businesses.show', $this->b))->assertSessionHas('success');

    $suspended = $this->b->fresh();
    expect($suspended->status)->toBe(Business::STATUS_SUSPENDED)
        ->and($suspended->suspended_by)->toBe($this->operator->id)
        ->and($suspended->suspension_reason)->toBe('Fraud check')
        ->and($suspended->runsBackgroundWork())->toBeFalse()
        ->and(consoleAudit('business.suspended')->business_id)->toBe($this->b->id);

    // The web.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $this->actingAs($owner)->get(route('dashboard'))->assertForbidden()->assertSeeText('This business is suspended');

    // The flat API.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    Sanctum::actingAs($owner, ['*']);
    $this->getJson('/api/products')->assertForbidden()->assertJsonPath('code', 'business_suspended');

    // /api/v1, with a token bound to the business.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $this->withToken($v1)->getJson('/api/v1/business/settings')->assertForbidden();

    // Business A works as before, also for the person in both.
    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $this->actingAs($this->fixture->a->owner)->get(route('dashboard'))->assertOk();
    $this->tenant->forget();
    $this->actingAs($this->fixture->both)->get(route('dashboard'))->assertOk();
});

it('reactivates a suspended business to the status it had, and lets it work again', function () {
    $owner = $this->fixture->b->owner;
    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['reason' => 'Check', 'confirm' => '1']);

    $this->actingAs($this->operator)->post(route('platform.businesses.reactivate', $this->b))->assertSessionHas('success');

    expect($this->b->fresh()->status)->toBe(Business::STATUS_ACTIVE)
        ->and($this->b->fresh()->suspended_at)->toBeNull()
        ->and(consoleAudit('business.reactivated'))->not->toBeNull();

    $this->app['auth']->forgetGuards();
    $this->tenant->forget();
    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
});

it('returns a business suspended while waiting for its owner\'s email to that status', function () {
    $this->b->update(['status' => Business::STATUS_PENDING_VERIFICATION]);

    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['reason' => 'Spam signup', 'confirm' => '1']);
    $this->actingAs($this->operator)->post(route('platform.businesses.reactivate', $this->b));

    expect($this->b->fresh()->status)->toBe(Business::STATUS_PENDING_VERIFICATION);
});

it('refuses to suspend a closing business, or reactivate one that isn\'t suspended', function () {
    $this->b->update(['status' => Business::STATUS_CLOSING]);
    $this->actingAs($this->operator)->post(route('platform.businesses.suspend', $this->b), ['reason' => 'x', 'confirm' => '1'])->assertSessionHas('error');
    expect($this->b->fresh()->status)->toBe(Business::STATUS_CLOSING);

    $a = $this->fixture->a->business;
    $this->actingAs($this->operator)->post(route('platform.businesses.reactivate', $a))->assertSessionHas('error');
    expect($a->fresh()->status)->toBe(Business::STATUS_ACTIVE);
});

it('changes a business\'s plan, locking the new price from the next payment', function () {
    [$cheap, $top] = [Plan::query()->orderBy('sort_order')->first(), Plan::query()->orderByDesc('sort_order')->first()];
    app(StartTrial::class)->execute($this->b);

    $this->actingAs($this->operator)->put(route('platform.businesses.plan', $this->b), ['plan_id' => $cheap->id])->assertSessionHas('success');

    $subscription = $this->tenant->run($this->b, fn () => Subscription::query()->first());
    expect($subscription->plan_id)->toBe($cheap->id)
        ->and((float) $subscription->price_at_signup)->toBe((float) $cheap->price_per_shop)
        ->and(consoleAudit('business.plan_changed')->new_values)->toMatchArray(['plan_before' => $top->id, 'plan' => $cheap->id])
        // Business A has no subscription and gets none.
        ->and($this->tenant->run($this->fixture->a->business, fn () => Subscription::query()->count()))->toBe(0);

    $this->actingAs($this->operator)->put(route('platform.businesses.plan', $this->b), ['plan_id' => 999999])->assertSessionHasErrors('plan_id');
});

it('starts a trial on the chosen plan for a business with no subscription', function () {
    $plan = Plan::query()->orderBy('sort_order')->first();

    $this->actingAs($this->operator)->put(route('platform.businesses.plan', $this->b), ['plan_id' => $plan->id]);

    $subscription = $this->tenant->run($this->b, fn () => Subscription::query()->first());
    expect($subscription->status)->toBe(Subscription::TRIALING)
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->trial_ends_at->isFuture())->toBeTrue();
});

it('extends a trial from its end, or from now once it has ended', function () {
    app(StartTrial::class)->execute($this->b);
    $subscription = fn () => $this->tenant->run($this->b, fn () => Subscription::query()->first());
    $end = $subscription()->trial_ends_at;

    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 10])->assertSessionHas('success');
    expect($subscription()->trial_ends_at->timestamp)->toBe($end->copy()->addDays(10)->timestamp);

    // An ended trial is extended from now.
    $this->tenant->run($this->b, fn () => Subscription::query()->update(['trial_ends_at' => now()->subDays(3)]));
    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 7]);
    expect($subscription()->trial_ends_at->isSameDay(now()->addDays(7)))->toBeTrue()
        ->and(consoleAudit('business.trial_extended')->new_values['days'])->toBe(7);
});

it('refuses extending a trial that isn\'t running a trial, or by too many days', function () {
    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 10])->assertSessionHas('error');

    app(StartTrial::class)->execute($this->b);
    $this->tenant->run($this->b, fn () => Subscription::query()->update(['status' => Subscription::ACTIVE, 'current_period_end' => now()->addMonth()]));
    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 10])->assertSessionHas('error');

    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 91])->assertSessionHasErrors('days');
    $this->actingAs($this->operator)->post(route('platform.businesses.trial', $this->b), ['days' => 0])->assertSessionHasErrors('days');
});
