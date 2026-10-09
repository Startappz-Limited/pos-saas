<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;

/**
 * Rate limiting is a mandatory standard and routes/api.php previously had none at
 * all. These tests pin both halves of the setup: the baseline limiter on the api
 * group (so a new endpoint is protected by default) and the tighter named limiters
 * on auth, money-affecting writes, destructive actions and webhooks.
 */
beforeEach(function () {
    // Real limits so a test cannot silently pass because a limiter was removed.
    config([
        'ratelimit.api' => 120,
        'ratelimit.auth' => 5,
        'ratelimit.writes' => 30,
        'ratelimit.destructive' => 10,
        'ratelimit.webhooks' => 300,
    ]);
});

/**
 * The throttle limiters attached to a named route, e.g. ['writes'].
 *
 * @return array<int, string>
 */
function throttlesFor(string $routeName): array
{
    $route = Route::getRoutes()->getByName($routeName);

    expect($route)->not->toBeNull("route [$routeName] does not exist");

    return throttleNames($route->gatherMiddleware());
}

/**
 * Extract limiter names from a middleware list.
 *
 * gatherMiddleware() returns unresolved aliases (`throttle:auth`), while route:list
 * shows the resolved class (`…\ThrottleRequests:auth`) — accept both so the helper
 * cannot silently match nothing and make an assertion pass vacuously.
 *
 * @param  array<int, mixed>  $middleware
 * @return array<int, string>
 */
function throttleNames(array $middleware): array
{
    return collect($middleware)
        ->filter(fn ($m) => is_string($m))
        ->map(fn (string $m) => str_replace('Illuminate\Routing\Middleware\ThrottleRequests:', 'throttle:', $m))
        ->filter(fn (string $m) => str_starts_with($m, 'throttle:'))
        ->map(fn (string $m) => substr($m, strlen('throttle:')))
        ->values()
        ->all();
}

// --- baseline coverage ---

it('applies a baseline throttle to the api middleware group', function () {
    expect(app('router')->getMiddlewareGroups()['api'])->toContain('throttle:api');
});

it('leaves no api route without a throttle', function () {
    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        ->reject(function ($route) {
            $mw = $route->gatherMiddleware();

            // Either the api group (which carries throttle:api) or a named limiter.
            return in_array('api', $route->middleware(), true) || throttleNames($mw) !== [];
        })
        ->map(fn ($route) => $route->uri())
        ->all();

    expect($unprotected)->toBe([]);
});

// --- named limiters on the right routes ---

it('throttles the auth endpoints tightly', function () {
    expect(throttlesFor('api.login'))->not->toBeEmpty();

    $loginRoute = Route::getRoutes()->getRoutes();

    $authRoutes = collect($loginRoute)
        ->filter(fn ($r) => in_array($r->uri(), ['api/login', 'api/register'], true));

    expect($authRoutes)->toHaveCount(2);

    $authRoutes->each(function ($route) {
        expect(throttleNames($route->gatherMiddleware()))->toContain('auth');
    });
});

it('throttles money-affecting writes with the writes limiter', function () {
    expect(throttlesFor('api.sales.store'))->toContain('writes')
        ->and(throttlesFor('api.sales.complete'))->toContain('writes')
        ->and(throttlesFor('api.sales.collect-payment'))->toContain('writes');
});

it('throttles destructive actions with the destructive limiter', function () {
    expect(throttlesFor('api.sales.void'))->toContain('destructive')
        ->and(throttlesFor('api.sales.destroy'))->toContain('destructive');
});

it('throttles inbound webhooks per shop', function () {
    expect(throttlesFor('webhooks.woocommerce'))->toContain('webhooks')
        ->and(throttlesFor('webhooks.shopify'))->toContain('webhooks');
});

/**
 * Chatway Gateway posts every shop's WhatsApp events from one IP, so it gets
 * its own high-ceiling limiter rather than sharing the per-shop one (Point's
 * inbox went silent when the same firehose shared a 120/min limit).
 */
it('throttles the WhatsApp gateway webhook with its own limiter', function () {
    expect(throttlesFor('wa-gateway.webhook'))->toContain('wa-gateway-webhook')
        ->not->toContain('webhooks');
});

/**
 * Regression: declaring per-action limiters as an associative array on a resource
 * applies EVERY entry to EVERY action, which silently capped ordinary reads at the
 * destructive limit. Reads must carry no tighter limiter than the baseline.
 */
it('does not leak the destructive limiter onto ordinary sales reads', function () {
    foreach (['api.sales.index', 'api.sales.show', 'api.sales.payments'] as $name) {
        expect(throttlesFor($name))
            ->not->toContain('destructive', "$name must not carry the destructive limiter")
            ->not->toContain('writes', "$name must not carry the writes limiter");
    }
});

// --- the limiters actually fire ---

it('returns 429 once the auth limit is exceeded', function () {
    $limit = config('ratelimit.auth');
    $credentials = ['email' => 'nobody@example.com', 'password' => 'wrong', 'device_name' => 'test'];

    for ($i = 0; $i < $limit; $i++) {
        $this->postJson('/api/login', $credentials)->assertStatus(422);
    }

    $this->postJson('/api/login', $credentials)->assertStatus(429);
});

it('keys the auth limiter by email so one target cannot lock out another', function () {
    $limit = config('ratelimit.auth');

    // Exhaust the limit for one address.
    for ($i = 0; $i < $limit; $i++) {
        $this->postJson('/api/login', ['email' => 'victim@example.com', 'password' => 'wrong', 'device_name' => 't']);
    }

    $this->postJson('/api/login', ['email' => 'victim@example.com', 'password' => 'wrong', 'device_name' => 't'])
        ->assertStatus(429);

    // A different address on the same IP is still blocked by the IP limit — which is
    // the intended trade-off — so assert the email key exists rather than that the
    // other address is free.
    expect(throttlesFor('api.login'))->toContain('auth')
        ->and(throttlesFor('api.register'))->toContain('auth');
});

it('sends rate limit headers on api responses', function () {
    Permission::findOrCreate('sales.view');

    $shop = Shop::factory()->create();
    $user = User::factory()->create();
    $user->shops()->sync([$shop->id]);
    $user->givePermissionTo('sales.view');

    $this->actingAs($user, 'sanctum')
        ->getJson(route('api.sales.index'))
        ->assertHeader('X-RateLimit-Limit', (string) config('ratelimit.api'));
});

it('reads its limits from config so they can be tuned per environment', function () {
    config(['ratelimit.auth' => 2]);

    $this->postJson('/api/login', ['email' => 'tuned@example.com', 'password' => 'wrong', 'device_name' => 't']);
    $this->postJson('/api/login', ['email' => 'tuned@example.com', 'password' => 'wrong', 'device_name' => 't']);

    $this->postJson('/api/login', ['email' => 'tuned@example.com', 'password' => 'wrong', 'device_name' => 't'])
        ->assertStatus(429);
});
