<?php

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\TwoBusinesses;

/**
 * Deactivation instead of self-delete (stage 3): the login stays, can't sign
 * in, loses its tokens and keeps its memberships; the only owner of a
 * business can't leave it ownerless.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    app(TenantContext::class)->forget();
});

it('deactivates a member: signed out, tokens revoked, no sign-in, memberships kept', function () {
    $both = $this->fixture->both;
    $both->createToken('flat');
    $this->postJson('/api/v1/auth/sign-in', ['email' => $both->email, 'password' => 'password', 'device_name' => 'Till']);
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->actingAs($both)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    $this->assertGuest();
    expect($both->fresh()->status->value)->toBe('inactive')
        ->and(PersonalAccessToken::where('tokenable_id', $both->id)->count())->toBe(0)
        ->and($both->memberships()->where('status', 'active')->count())->toBe(2);

    $this->post('/login', ['email' => $both->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('refuses the only owner of a business, and lets an owner with a co-owner go', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->from('/profile')->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'password');
    expect($owner->fresh()->status->value)->toBe('active');
    $this->assertAuthenticated();

    $coOwner = User::factory()->memberOf($this->a->business)->create();
    app(TenantContext::class)->run($this->a->business, fn () => $coOwner->assignRole('admin'));
    app(TenantContext::class)->forget();
    app('auth')->forgetGuards();

    $this->actingAs($owner->fresh())->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    expect($owner->fresh()->status->value)->toBe('inactive');
});
