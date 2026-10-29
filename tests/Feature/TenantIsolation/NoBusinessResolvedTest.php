<?php

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

    foreach (['/api/v1/billing', '/api/v1/businesses', '/api/v1/business/integrations', '/api/v1/business/onboarding',
        '/api/v1/business/settings', '/api/v1/registers', "/api/v1/sales/{$saleOfA}/receipt", "/api/v1/sales/{$saleOfB}/receipt",
        '/api/v1/sync/lease', '/api/v1/sync/pull'] as $uri) {
        $response = $this->withToken($token)->getJson($uri);

        expect($response->getStatusCode())->toBeLessThan(500, $uri)
            ->and($response->getContent())->not->toContain('Alpha', $uri)->not->toContain('Bravo', $uri)
            ->not->toContain('Business A', $uri)->not->toContain('Business B', $uri);

        app('auth')->forgetGuards();
        app(TenantContext::class)->forget();
    }
})->with([Business::STATUS_SUSPENDED, Business::STATUS_CLOSING]);
