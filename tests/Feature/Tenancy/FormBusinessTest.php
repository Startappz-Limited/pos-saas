<?php

use App\Support\Tenancy\TenantContext;
use Tests\Support\TwoBusinesses;

/**
 * One active business per session (decision 4.9, stage 3): a form carries the
 * business it was loaded for, and a submission after a switch is refused.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a->business;
    $this->b = $this->fixture->b->business;
    $this->both = $this->fixture->both;
    foreach ([$this->a, $this->b] as $business) {
        app(TenantContext::class)->run($business, fn () => $this->both->givePermissionTo('customers.view', 'customers.create'));
    }
    $this->both->forceFill(['default_business_id' => $this->a->id])->save();
    app(TenantContext::class)->forget();
    $this->post('/login', ['email' => $this->both->email, 'password' => 'password'])->assertRedirect();
});

it('writes the business a form was loaded for into the form', function () {
    $this->get('/customers/create')->assertOk()
        ->assertSee('name="_business" value="'.$this->a->uuid.'"', false);
});

it('refuses a form loaded for business A once the session is in B, and saves nothing', function () {
    $this->post(route('businesses.switch', $this->b), ['_business' => $this->a->uuid])->assertRedirect();

    $this->from('/customers')->post(route('businesses.default', $this->a), ['_business' => $this->a->uuid])
        ->assertRedirect('/customers')
        ->assertSessionHas('error', 'This business was switched; reload the page.');

    expect($this->both->fresh()->default_business_id)->toBe($this->a->id);
});

it('accepts a form loaded for the active business, and one that names none', function () {
    $this->post(route('businesses.default', $this->b), ['_business' => $this->a->uuid])->assertSessionMissing('error');
    expect($this->both->fresh()->default_business_id)->toBe($this->b->id);

    $this->post(route('businesses.default', $this->a))->assertSessionMissing('error');
    expect($this->both->fresh()->default_business_id)->toBe($this->a->id);
});

it('refuses a script that names another business in the header', function () {
    $this->postJson(route('businesses.default', $this->b), [], ['X-Business' => $this->b->uuid])
        ->assertStatus(409);
});

it('never moves a shop to another business', function () {
    $shop = $this->fixture->a->shops[0];

    expect(fn () => app(TenantContext::class)->run($this->a, fn () => $shop->update(['business_id' => $this->b->id])))
        ->toThrow(LogicException::class);
});
