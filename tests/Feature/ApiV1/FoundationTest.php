<?php

use App\Enums\ApiErrorCode;
use App\Support\Tenancy\TenantContext;
use Tests\Support\TwoBusinesses;

/**
 * The `/api/v1` foundation, Phase 7 (step 1): every response has the same
 * five keys and every error a code from the registry; the flat API is left
 * as it was; and one business's traffic has its own rate limit on both APIs.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();
});

/**
 * A `/api/v1` token for $user bound to $business, through sign-in.
 */
function foundationToken($user, $business): string
{
    app(TenantContext::class)->forget();

    return test()->postJson('/api/v1/auth/sign-in', [
        'email' => $user->email, 'password' => 'password', 'device_name' => 'Till', 'business' => $business->uuid,
    ])->assertOk()->json('data.token');
}

it('gives a successful /api/v1 response the five keys, with no code', function () {
    $token = foundationToken($this->fixture->a->owner, $this->fixture->a->business);

    $this->withToken($token)->getJson('/api/v1/businesses')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data', 'meta', 'code'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('code', null);
});

it('gives every /api/v1 error a code, including the framework\'s own', function () {
    // No token: the auth middleware's 401.
    $this->getJson('/api/v1/businesses')->assertUnauthorized()
        ->assertJsonStructure(['success', 'message', 'data', 'meta', 'code'])
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', ApiErrorCode::Unauthenticated->value);

    // Validation: 422 with the field errors kept.
    $this->postJson('/api/v1/auth/sign-in', [])->assertUnprocessable()
        ->assertJsonPath('code', ApiErrorCode::ValidationFailed->value)
        ->assertJsonValidationErrors(['email', 'password']);

    // A flat API token on /api/v1.
    $flat = $this->fixture->a->owner->createToken('flat')->plainTextToken;
    app('auth')->forgetGuards();
    $this->withToken($flat)->getJson('/api/v1/businesses')->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::BusinessTokenRequired->value);
});

it('keeps a code a refusal already carries', function () {
    $owner = $this->fixture->a->owner;
    $token = foundationToken($owner, $this->fixture->a->business);
    $owner->forceFill(['status' => 'inactive'])->save();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/businesses')->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::AccountInactive->value)
        ->assertJsonPath('meta', null);
});

it('leaves the flat API\'s responses as they were', function () {
    $token = $this->fixture->a->owner->createToken('flat')->plainTextToken;

    $body = $this->withToken($token)->getJson('/api/categories')->assertOk()->json();

    expect($body)->toHaveKey('success')->not->toHaveKey('meta')->not->toHaveKey('code');
});

it('maps each status to a code, and describes every code', function () {
    expect(ApiErrorCode::forStatus(401))->toBe(ApiErrorCode::Unauthenticated)
        ->and(ApiErrorCode::forStatus(404))->toBe(ApiErrorCode::NotFound)
        ->and(ApiErrorCode::forStatus(422))->toBe(ApiErrorCode::ValidationFailed)
        ->and(ApiErrorCode::forStatus(429))->toBe(ApiErrorCode::TooManyRequests)
        ->and(ApiErrorCode::forStatus(500))->toBe(ApiErrorCode::ServerError);

    foreach (ApiErrorCode::cases() as $code) {
        expect($code->description())->not->toBeEmpty();
    }
});

it('limits one business\'s API traffic as a whole, on both APIs, without touching another business', function () {
    config(['ratelimit.api' => 100, 'ratelimit.api_business' => 3]);
    $a = $this->fixture->a;

    $ownerToken = $a->owner->createToken('flat')->plainTextToken;
    $cashierToken = $a->cashier->createToken('flat')->plainTextToken;
    $otherToken = $this->fixture->b->owner->createToken('flat')->plainTextToken;

    foreach (range(1, 3) as $ignored) {
        app('auth')->forgetGuards();
        $this->withToken($ownerToken)->getJson('/api/user')->assertOk();
    }

    // A different member of the same business is now over the business's limit.
    app('auth')->forgetGuards();
    $this->withToken($cashierToken)->getJson('/api/user')->assertTooManyRequests();

    // Another business is unaffected.
    app('auth')->forgetGuards();
    $this->withToken($otherToken)->getJson('/api/user')->assertOk();
});

it('says too_many_requests when /api/v1 is rate limited', function () {
    config(['ratelimit.api' => 1, 'ratelimit.api_business' => 100]);
    $token = foundationToken($this->fixture->a->owner, $this->fixture->a->business);

    $this->withToken($token)->getJson('/api/v1/businesses')->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/businesses')->assertTooManyRequests()
        ->assertJsonPath('code', ApiErrorCode::TooManyRequests->value);
});
