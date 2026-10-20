<?php

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\TwoBusinesses;

/**
 * `/api/v1` sign-in, the business list, switching and sign-out (decision 4.4,
 * stage 3): every token is bound to one business, by the same rules as the
 * web sign-in.
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
 * @return TestResponse<JsonResponse>
 */
function v1SignIn(User $user, array $extra = []): TestResponse
{
    app(TenantContext::class)->forget();

    return test()->postJson('/api/v1/auth/sign-in', ['email' => $user->email, 'password' => 'password', 'device_name' => 'Till 1', ...$extra]);
}

/**
 * A request with $token only (no session, no remembered user).
 *
 * @return TestResponse<JsonResponse>
 */
function withToken(string $token, string $method, string $uri): TestResponse
{
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    return test()->withHeaders(['Authorization' => "Bearer {$token}"])->json($method, $uri);
}

function tokenBusiness(string $plain): ?int
{
    return PersonalAccessToken::findToken($plain)?->business_id;
}

it('signs a user with one business straight in to it, with no picker', function () {
    $response = v1SignIn($this->fixture->a->owner)->assertOk()
        ->assertJsonPath('data.business_required', false)
        ->assertJsonPath('data.business.uuid', $this->fixture->a->business->uuid)
        ->assertJsonCount(1, 'data.businesses');

    expect(tokenBusiness($response->json('data.token')))->toBe($this->fixture->a->business->id);
});

it('signs a member of two in to their default and lists both', function () {
    $response = v1SignIn($this->both)->assertOk()
        ->assertJsonPath('data.business.uuid', $this->fixture->a->business->uuid)
        ->assertJsonPath('data.business.is_default', true)
        ->assertJsonCount(2, 'data.businesses');

    withToken($response->json('data.token'), 'GET', '/api/v1/businesses')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['uuid' => $this->fixture->a->business->uuid, 'is_current' => true])
        ->assertJsonFragment(['uuid' => $this->fixture->b->business->uuid, 'is_current' => false]);
});

it('asks a member of two with no usable default to choose, and the choice becomes the default', function () {
    $this->both->forceFill(['default_business_id' => null])->save();

    v1SignIn($this->both)->assertOk()
        ->assertJsonPath('data.business_required', true)
        ->assertJsonPath('data.token', null)
        ->assertJsonCount(2, 'data.businesses');
    expect(PersonalAccessToken::count())->toBe(0);

    $response = v1SignIn($this->both, ['business' => $this->fixture->b->business->uuid])->assertOk()
        ->assertJsonPath('data.business.uuid', $this->fixture->b->business->uuid);

    expect(tokenBusiness($response->json('data.token')))->toBe($this->fixture->b->business->id)
        ->and($this->both->fresh()->default_business_id)->toBe($this->fixture->b->business->id);
});

it('refuses a business the user cannot sign in to', function (string $case) {
    $business = match ($case) {
        'another business' => Business::factory()->create(),
        'suspended' => tap($this->fixture->b->business)->update(['status' => 'suspended']),
    };

    v1SignIn($this->both, ['business' => $business->uuid])->assertUnprocessable()->assertJsonValidationErrors('business');
    expect(PersonalAccessToken::count())->toBe(0);
})->with(['another business', 'suspended']);

it('refuses wrong credentials, an inactive account and a login with no business', function () {
    $this->postJson('/api/v1/auth/sign-in', ['email' => $this->both->email, 'password' => 'wrong', 'device_name' => 'x'])
        ->assertUnprocessable();

    $nobody = User::factory()->create();
    $nobody->memberships()->delete();
    v1SignIn($nobody)->assertForbidden();

    $this->both->update(['status' => 'inactive']);
    v1SignIn($this->both)->assertUnprocessable();

    expect(PersonalAccessToken::count())->toBe(0);
});

it('switches by issuing a token for the other business and revoking the one used', function () {
    $old = v1SignIn($this->both)->json('data.token');

    $new = withToken($old, 'POST', '/api/v1/businesses/'.$this->fixture->b->business->uuid.'/switch')->assertOk()
        ->assertJsonPath('data.business.uuid', $this->fixture->b->business->uuid)
        ->json('data.token');

    expect(tokenBusiness($new))->toBe($this->fixture->b->business->id)
        ->and(PersonalAccessToken::findToken($old))->toBeNull();
    withToken($old, 'GET', '/api/v1/businesses')->assertUnauthorized();

    // The new token sees only B, on the flat API too.
    withToken($new, 'GET', '/api/customers')->assertOk()
        ->assertSee('Bravo Customer')->assertDontSee('Alpha Customer');

    // Switching isn't choosing a default.
    expect($this->both->fresh()->default_business_id)->toBe($this->fixture->a->business->id);
});

it('refuses to switch to a business the user cannot work in', function () {
    $token = v1SignIn($this->fixture->a->owner)->json('data.token');

    withToken($token, 'POST', '/api/v1/businesses/'.$this->fixture->b->business->uuid.'/switch')->assertNotFound();
    expect(PersonalAccessToken::findToken($token))->not->toBeNull()
        ->and(PersonalAccessToken::count())->toBe(1);
});

it('cuts a token off as soon as the membership is removed or suspended', function () {
    $token = v1SignIn($this->both, ['business' => $this->fixture->a->business->uuid])->json('data.token');
    withToken($token, 'GET', '/api/customers')->assertOk()->assertSee('Alpha Customer');

    BusinessMembership::where('business_id', $this->fixture->a->business->id)->where('user_id', $this->both->id)
        ->update(['status' => 'suspended']);

    // Not quietly moved to B: the token is for A only.
    withToken($token, 'GET', '/api/customers')->assertDontSee('Alpha Customer')->assertDontSee('Bravo Customer');
    withToken($token, 'POST', '/api/v1/businesses/'.$this->fixture->a->business->uuid.'/switch')->assertNotFound();

    // It can still list what's left and switch there.
    withToken($token, 'GET', '/api/v1/businesses')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $this->fixture->b->business->uuid)->assertJsonPath('data.0.is_current', false);
    withToken($token, 'POST', '/api/v1/businesses/'.$this->fixture->b->business->uuid.'/switch')->assertOk();
});

it('signs out by revoking the token', function () {
    $token = v1SignIn($this->both)->json('data.token');

    withToken($token, 'POST', '/api/v1/auth/sign-out')->assertOk();

    expect(PersonalAccessToken::findToken($token))->toBeNull();
});

it('refuses a flat API token on /api/v1 with 403, not 401', function () {
    $flat = $this->both->createToken('old app')->plainTextToken;

    withToken($flat, 'GET', '/api/v1/businesses')->assertForbidden();
});

it('keeps flat API tokens on the default business', function () {
    $flat = $this->both->createToken('old app')->plainTextToken;

    withToken($flat, 'GET', '/api/customers')->assertOk()->assertSee('Alpha Customer')->assertDontSee('Bravo Customer');
});
