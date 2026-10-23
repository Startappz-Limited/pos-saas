<?php

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\Support\TwoBusinesses;

/**
 * The users list and a user's page show each person's real status in the
 * business (they read a non-existent `is_active` and showed everyone as
 * Inactive), and a newly signed-up owner is active once their email is
 * verified.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    Notification::fake();
});

/**
 * The text of the users list row holding $email.
 */
function userRow(string $html, string $email): string
{
    preg_match_all('/<tr\b.*?<\/tr>/s', $html, $rows);
    $row = collect($rows[0])->first(fn (string $row): bool => str_contains($row, $email)) ?? '';

    return trim(preg_replace('/\s+/', ' ', strip_tags($row)));
}

it('shows a newly signed-up owner as active once their email is verified', function () {
    $this->post('/register', [
        'name' => 'New Owner', 'business_name' => 'Fresh Shop', 'email' => 'new.owner@freshshop.co.ke',
        'password' => 'Str0ng-P0S-Pass!', 'password_confirmation' => 'Str0ng-P0S-Pass!',
    ]);
    $owner = User::where('email', 'new.owner@freshshop.co.ke')->firstOrFail();

    $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $owner->id, 'hash' => sha1($owner->email)]));
    app(TenantContext::class)->forget();

    $business = Business::where('name', 'Fresh Shop')->firstOrFail();
    expect($business->status)->toBe(Business::STATUS_ACTIVE)
        ->and(BusinessMembership::where('business_id', $business->id)->where('user_id', $owner->id)->value('status'))->toBe('active');

    $html = $this->get(route('users.index'))->assertOk()->getContent();
    $row = userRow($html, 'new.owner@freshshop.co.ke');

    expect($row)->toContain('Active')->not->toContain('Inactive')->not->toContain('Email not verified');
});

it('opens the business and the membership on verification, even from an inactive membership', function () {
    $owner = User::factory()->unverified()->create(['email' => 'pending@example.test']);
    $business = Business::factory()->create(['status' => Business::STATUS_PENDING_VERIFICATION]);
    $owner->joinBusiness($business);
    BusinessMembership::where('business_id', $business->id)->where('user_id', $owner->id)->update(['status' => 'inactive']);

    $this->actingAs($owner)->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $owner->id, 'hash' => sha1($owner->email)]));

    expect($business->fresh()->status)->toBe(Business::STATUS_ACTIVE)
        ->and(BusinessMembership::where('business_id', $business->id)->where('user_id', $owner->id)->value('status'))->toBe('active');
});

it('shows each person\'s status in this business, and an unverified email', function () {
    $fixture = TwoBusinesses::create();
    $both = $fixture->both;
    app(TenantContext::class)->forget();
    $this->actingAs($fixture->a->owner)->post(route('users.suspend', $both));
    $fixture->a->cashier->forceFill(['email_verified_at' => null])->save();

    app(TenantContext::class)->forget();
    $html = $this->actingAs($fixture->a->owner)->get(route('users.index'))->assertOk()->getContent();

    expect(userRow($html, $both->email))->toContain('Suspended')
        ->and(userRow($html, $fixture->a->owner->email))->toContain('Active')->not->toContain('Inactive')
        ->and(userRow($html, $fixture->a->cashier->email))->toContain('Email not verified');

    // The same person is active in B.
    app(TenantContext::class)->forget();
    app('auth')->forgetGuards();
    $html = $this->actingAs($fixture->b->owner)->get(route('users.index'))->assertOk()->getContent();
    expect(userRow($html, $both->email))->toContain('Active')->not->toContain('Suspended');

    // And on their own page in A.
    app(TenantContext::class)->forget();
    app('auth')->forgetGuards();
    $this->actingAs($fixture->a->owner)->get(route('users.show', $both))->assertOk()->assertSee('Suspended');
});

it('shows the real counts on the users page', function () {
    $fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();
    $this->actingAs($fixture->a->owner)->post(route('users.suspend', $fixture->both));

    app(TenantContext::class)->forget();
    $html = preg_replace('/\s+/', ' ', strip_tags($this->actingAs($fixture->a->owner)->get(route('users.index'))->getContent()));

    preg_match('/Total Users (\d+) Active Users (\d+) .*? Inactive or Suspended (\d+)/', $html, $counts);
    $members = User::query()->whereHas('memberships', fn ($q) => $q->where('business_id', $fixture->a->business->id))->count();

    // Everyone in A, all active but the member of both, suspended here.
    expect((int) ($counts[1] ?? -1))->toBe($members)
        ->and((int) $counts[2])->toBe($members - 1)
        ->and((int) $counts[3])->toBe(1)
        ->and($html)->not->toContain('+5.2%');
});
