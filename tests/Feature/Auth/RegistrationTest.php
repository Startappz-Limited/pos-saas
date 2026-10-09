<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Spatie\Honeypot\Honeypot;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('a new user registers as an admin with their own business', function () {
    $existing = Business::factory()->create();

    $this->post('/register', [
        'name' => 'Jane Owner',
        'email' => 'jane@example.com',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
    ]);

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->hasRole(Role::ADMIN))->toBeTrue()
        ->and($user->isSuperAdmin())->toBeFalse()
        ->and($user->business_id)->not->toBeNull()
        ->and($user->business_id)->not->toBe($existing->id)
        ->and($user->business->owner_id)->toBe($user->id);
});

test('a newly registered admin lands on the dashboard, not the no-shop page', function () {
    $this->post('/register', [
        'name' => 'Jane Owner',
        'email' => 'jane@example.com',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
    ]);

    $this->get(route('dashboard'))->assertOk();
});

test('the registration form carries the honeypot', function () {
    $this->get('/register')->assertOk()->assertSee(app(Honeypot::class)->validFromFieldName());
});

test('a bot that fills in the honeypot is not registered', function () {
    $honeypot = app(Honeypot::class);

    $this->travel(5)->seconds();

    $this->post('/register', [
        'name' => 'Spam Bot',
        'email' => 'bot@example.com',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
        $honeypot->unrandomizedNameFieldName() => 'I am a bot',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
    ]);

    $this->assertGuest();
    expect(User::where('email', 'bot@example.com')->exists())->toBeFalse();
});

test('a person who leaves the honeypot empty is registered', function () {
    $honeypot = app(Honeypot::class);
    $validFrom = $honeypot->encryptedValidFrom();

    // The timestamp check rejects forms submitted faster than a human could
    $this->travel(5)->seconds();

    $this->post('/register', [
        'name' => 'Real Person',
        'email' => 'person@example.com',
        'password' => 'Str0ng-P0S-Pass!',
        'password_confirmation' => 'Str0ng-P0S-Pass!',
        $honeypot->unrandomizedNameFieldName() => '',
        $honeypot->validFromFieldName() => $validFrom,
    ]);

    $this->assertAuthenticated();
});
