<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\BusinessMembership;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;
use Tests\Support\TwoBusinesses;

/**
 * Switching between businesses and API tokens (Phase 2). A member of both
 * businesses acts for one at a time: the session's chosen business on the
 * web, re-checked against their memberships on every request, and their
 * default business with a flat API token. The switcher's screens come in
 * Phase 3; `/api/v1` tokens bound to one business in Phase 6, when their rows
 * are added here.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->both = $this->fixture->both;
    // The customer list in each business (permissions are per business).
    foreach ([$this->fixture->a->business, $this->fixture->b->business] as $business) {
        app(TenantContext::class)->run($business, fn () => $this->both->givePermissionTo('customers.view'));
    }
    app(TenantContext::class)->forget();
});

it('shows only the chosen business after switching, then only the other after switching back', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;

    $this->actingAs($this->both)->withSession([ResolveTenant::SESSION_KEY => $b->business->id]);
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');

    $this->withSession([ResolveTenant::SESSION_KEY => $a->business->id]);
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer');
});

it('accepts nothing of the other business after switching', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;

    // Acting for A, B's customer can't be read or changed.
    $this->actingAs($this->both)->withSession([ResolveTenant::SESSION_KEY => $a->business->id]);
    app(TenantContext::class)->forget();
    $this->get(route('customers.show', $b->customer))->assertNotFound();
    $this->put(route('customers.update', $b->customer), ['name' => 'Taken over'])->assertNotFound();

    expect(app(TenantContext::class)->run($b->business, fn () => $b->customer->fresh()->name))->toBe('Bravo Customer');
});

it('ignores a chosen business the user is not an active member of', function () {
    $outsider = $this->fixture->a->owner;

    // A session claiming business B, which A's owner doesn't belong to.
    $this->actingAs($outsider)->withSession([ResolveTenant::SESSION_KEY => $this->fixture->b->business->id]);
    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer');

    // A suspended membership no longer opens the business either.
    BusinessMembership::query()->where('user_id', $this->both->id)->where('business_id', $this->fixture->b->business->id)
        ->update(['status' => 'suspended']);
    $this->actingAs($this->both)->withSession([ResolveTenant::SESSION_KEY => $this->fixture->b->business->id]);
    app(TenantContext::class)->forget();
    $this->get('/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer');
});

it('acts with a flat API token only for the default business', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;

    Sanctum::actingAs($this->both, ['*']);
    app(TenantContext::class)->forget();
    $this->getJson(route('api.customers.show', $a->customer))->assertOk();
    $this->getJson(route('api.customers.show', $b->customer))->assertNotFound();

    // A session choice has no effect on a token: the default business decides.
    $this->both->forceFill(['default_business_id' => $b->business->id])->save();
    Sanctum::actingAs($this->both->fresh(), ['*']);
    app(TenantContext::class)->forget();
    $this->getJson(route('api.customers.show', $b->customer))->assertOk();
    $this->getJson(route('api.customers.show', $a->customer))->assertNotFound();
});
