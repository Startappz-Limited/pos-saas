<?php

use App\Actions\Business\RemoveFromBusiness;
use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Tests\Support\TwoBusinesses;

/*
 * A request with a signed-in user but no business (an `/api/v1` token bound to
 * a business that was then suspended or closed, or that the person left)
 * reaches no business's rows: the tenant scopes and `restrictToShops()` add
 * `1 = 0`, they never drop the filter (checked 29 October 2026, hotfix
 * fix/api-authz). Run with strict mode off, as in production; strict mode
 * throws MissingTenantContext instead.
 *
 * Since stage 10a, step 1, `EnsureBusinessToken` refuses such a token outright
 * (403 `business_suspended` or `no_active_business`) on every call but
 * sign-out, rather than letting it reach a (now-empty) endpoint.
 */

beforeEach(function () {
    $this->fixture = TwoBusinesses::create();
    config(['tenancy.strict' => false]);
});

/**
 * A `/api/v1` token for $user bound to $business.
 */
function boundTokenFor(User $user, Business $business): string
{
    app(TenantContext::class)->forget();
    $token = $user->createToken('till', ['*']);
    $token->accessToken->forceFill(['business_id' => $business->id])->save();

    return $token->plainTextToken;
}

it('returns no business\'s data to a bound token once its business is suspended or closing', function (string $status) {
    $owner = $this->fixture->b->owner;
    $token = boundTokenFor($owner, $this->fixture->b->business);

    // The control: while the business is active, the token sees it.
    expect($this->withToken($token)->getJson("/api/v1/sales/{$this->fixture->b->sale->uuid}/receipt")->assertOk()->getContent())
        ->toContain('Bravo');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->fixture->b->business->forceFill(['status' => $status])->save();
    app(TenantContext::class)->forget();

    $saleOfA = $this->fixture->a->sale->uuid;
    $saleOfB = $this->fixture->b->sale->uuid;

    $expectedCode = $status === Business::STATUS_SUSPENDED ? 'business_suspended' : 'no_active_business';

    foreach (['/api/v1/billing', '/api/v1/business/integrations', '/api/v1/business/onboarding',
        '/api/v1/business/settings', '/api/v1/registers', "/api/v1/sales/{$saleOfA}/receipt", "/api/v1/sales/{$saleOfB}/receipt",
        '/api/v1/sync/lease', '/api/v1/sync/pull'] as $uri) {
        $response = $this->withToken($token)->getJson($uri);

        expect($response->getStatusCode())->toBe(403, $uri)
            ->and($response->json('code'))->toBe($expectedCode, $uri)
            ->and($response->json('success'))->toBeFalse($uri)
            ->and($response->getContent())->not->toContain('Alpha', $uri)->not->toContain('Bravo', $uri)
            ->not->toContain('Business A', $uri)->not->toContain('Business B', $uri);

        app('auth')->forgetGuards();
        app(TenantContext::class)->forget();
    }

    // Exempt, so the app can always get itself unstuck: listing reachable
    // businesses (none, here — B is the only one and it's no longer active),
    // switching (refused by the controller itself, since A isn't the token's
    // business and B isn't enterable), and signing out.
    $this->withToken($token)->getJson('/api/v1/businesses')->assertOk()->assertJsonCount(0, 'data');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $this->withToken($token)->postJson('/api/v1/businesses/'.$this->fixture->a->business->uuid.'/switch')->assertNotFound();
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $this->withToken($token)->postJson('/api/v1/auth/sign-out')->assertOk();
})->with([Business::STATUS_SUSPENDED, Business::STATUS_CLOSING]);

it('deletes a removed member\'s bound tokens, and refuses one issued after between checks', function () {
    $a = $this->fixture->a;
    $cashier = $a->cashier;
    $existingToken = boundTokenFor($cashier, $a->business);

    app(TenantContext::class)->run($a->business, fn () => app(RemoveFromBusiness::class)->execute($cashier));
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    // Deleted on removal: no longer even a recognised token.
    $this->withToken($existingToken)->getJson('/api/v1/businesses')->assertStatus(401);

    // One issued between checks (a race, or a sync process) is refused too.
    // (/api/v1/businesses is exempt so a stuck token can still recover —
    // /api/v1/billing isn't, and demonstrates the refusal itself.)
    $lateToken = boundTokenFor($cashier, $a->business);
    $response = $this->withToken($lateToken)->getJson('/api/v1/billing');
    $response->assertStatus(403)->assertJson(['success' => false, 'code' => 'no_active_business']);
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();
    $this->withToken($lateToken)->getJson('/api/v1/businesses')->assertOk();
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->withToken($lateToken)->postJson('/api/v1/auth/sign-out')->assertOk();
});
