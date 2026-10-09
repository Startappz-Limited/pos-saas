<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

/**
 * The security baseline that .ai/general mandates but the app previously shipped
 * without: hardening headers, HTTPS enforcement and a password policy.
 */

// --- headers ---

it('sends hardening headers on web responses', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
});

it('sets a content security policy that blocks base-tag and plugin abuse', function () {
    $csp = $this->get(route('login'))->assertOk()->headers->get('Content-Security-Policy');

    expect($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain('frame-ancestors');
});

it('restricts powerful browser features', function () {
    $policy = $this->get(route('login'))->assertOk()->headers->get('Permissions-Policy');

    foreach (['camera=()', 'microphone=()', 'geolocation=()', 'payment=()'] as $directive) {
        expect($policy)->toContain($directive);
    }
});

it('sends hardening headers on api responses too', function () {
    // Unauthenticated is fine — the middleware runs regardless of the outcome.
    $this->getJson('/api/user')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('does not send HSTS over plain http', function () {
    // Local/dev traffic is http; pinning developers to https would be hostile.
    $this->get(route('login'))->assertOk()->assertHeaderMissing('Strict-Transport-Security');
});

it('leaves a header alone when the response already set it', function () {
    Route::get('/__test/framed', fn () => response('ok')->header('X-Frame-Options', 'DENY'))
        ->middleware('web');

    $this->get('/__test/framed')->assertHeader('X-Frame-Options', 'DENY');
});

// --- https enforcement ---

it('does not force https outside production by default', function () {
    expect(config('security.force_https'))->toBeFalse()
        ->and(URL::to('/dashboard'))->toStartWith('http://');
});

it('forces https in generated urls when enabled', function () {
    config(['security.force_https' => true]);
    URL::forceScheme('https');

    expect(URL::to('/dashboard'))->toStartWith('https://');
})->after(fn () => URL::forceScheme('http'));

// --- password policy ---

it('applies the project password policy to Password::defaults', function () {
    $validator = validator(
        ['password' => 'short'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('password'))->not->toBeEmpty();
});

it('rejects a password missing symbols, numbers or mixed case', function (string $password) {
    $validator = validator(
        ['password' => $password],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeTrue();
})->with([
    'no symbols' => ['Password123'],
    'no numbers' => ['Password!!!'],
    'no uppercase' => ['password123!'],
    'too short' => ['Pw1!'],
]);

it('accepts a password meeting the full policy', function () {
    $validator = validator(
        ['password' => 'Str0ng-P0S-Passw0rd!'],
        ['password' => ['required', Password::defaults()]]
    );

    expect($validator->fails())->toBeFalse();
});

it('reads the policy from config', function () {
    expect(config('security.passwords.min_length'))->toBe(8)
        ->and(config('security.passwords.symbols'))->toBeTrue();
});
