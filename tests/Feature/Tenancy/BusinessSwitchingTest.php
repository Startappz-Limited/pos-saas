<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Tests\Support\TwoBusinesses;

/**
 * Sign-in, the business picker, the switcher and the default business
 * (decision 4.4, stage 3). One usable business opens directly; several open
 * the usable default; several with no usable default show the picker, and the
 * choice becomes the default. An inactive membership or a suspended business
 * is never opened.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->both = $this->fixture->both;
    foreach ([$this->fixture->a->business, $this->fixture->b->business] as $business) {
        app(TenantContext::class)->run($business, fn () => $this->both->givePermissionTo('customers.view'));
    }
    app(TenantContext::class)->forget();
});

/**
 * Signs in through the login form, as a real sign-in does.
 */
function signIn(User $user): void
{
    app(TenantContext::class)->forget();
    test()->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();
}

it('opens a user\'s only business straight away, with no picker', function () {
    signIn($this->fixture->a->owner);

    $this->get('/dashboard')->assertOk();
    $this->get(route('businesses.index'))->assertOk()->assertSee($this->fixture->a->business->name);
});

it('opens the only usable business when the default is no longer usable', function () {
    $owner = $this->fixture->a->owner;
    $owner->joinBusiness($this->fixture->b->business);
    $this->fixture->b->business->update(['status' => 'suspended']);
    $owner->forceFill(['default_business_id' => $this->fixture->b->business->id])->save();

    signIn($owner);

    $this->get('/dashboard')->assertOk();
    expect(session(ResolveTenant::SESSION_KEY))->toBe($this->fixture->a->business->id);
});

it('opens the default business of a member of two, and the switcher shows it', function () {
    signIn($this->both);

    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer')
        ->assertSee('Switch business');
});

it('shows the picker to a member of two with no usable default, and the choice becomes the default', function () {
    $this->both->forceFill(['default_business_id' => null])->save();
    signIn($this->both);

    $this->get('/customers')->assertRedirect(route('businesses.index'));
    $this->get(route('businesses.index'))->assertOk()->assertSee('Choose a business')
        ->assertSee($this->fixture->a->business->name)->assertSee($this->fixture->b->business->name);

    $this->post(route('businesses.switch', $this->fixture->b->business))->assertRedirect(route('dashboard'));

    expect($this->both->fresh()->default_business_id)->toBe($this->fixture->b->business->id);
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');
});

it('switches between businesses and only ever shows the active one, keeping the default', function () {
    signIn($this->both);

    $this->post(route('businesses.switch', $this->fixture->b->business))->assertRedirect(route('dashboard'));
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');

    $this->post(route('businesses.switch', $this->fixture->a->business));
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer');

    // A usable default stays as it was; switching isn't choosing a default.
    expect($this->both->fresh()->default_business_id)->toBe($this->fixture->a->business->id);
});

it('makes a business the default, so the next sign-in opens it', function () {
    signIn($this->both);
    $this->post(route('businesses.default', $this->fixture->b->business))->assertRedirect();
    expect($this->both->fresh()->default_business_id)->toBe($this->fixture->b->business->id);

    $this->post('/logout');
    signIn($this->both);

    $this->get('/customers')->assertOk()->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');
});

it('never opens a suspended business or one the membership of is inactive', function (string $case) {
    $b = $this->fixture->b->business;
    $case === 'suspended business'
        ? $b->update(['status' => 'suspended'])
        : BusinessMembership::where('business_id', $b->id)->where('user_id', $this->both->id)->update(['status' => 'inactive']);
    signIn($this->both);

    $this->post(route('businesses.switch', $b))->assertNotFound();
    $this->post(route('businesses.default', $b))->assertNotFound();
    $this->get(route('businesses.index'))->assertOk()->assertDontSee($b->name);

    // With B unusable, only A is left, so A opens with no picker and no switcher.
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Switch business');
})->with(['suspended business', 'inactive membership']);

it('refuses to open a business with no members by uuid', function () {
    $other = Business::factory()->create(['name' => 'Charlie Ltd']);
    signIn($this->both);

    $this->post(route('businesses.switch', $other))->assertNotFound();
    expect(session(ResolveTenant::SESSION_KEY))->not->toBe($other->id);
});
