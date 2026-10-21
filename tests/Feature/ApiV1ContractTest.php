<?php

use App\Support\Tenancy\TenantContext;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Support\ApiShape;
use Tests\Support\TwoBusinesses;

/**
 * The `/api/v1` contract (Phase 4 close): like the flat API's, it fails when
 * a recorded route is removed or changed, or an endpoint's status changes or
 * a recorded response key disappears or changes type; adding routes or keys
 * is allowed. Recorded with the two-business fixture, signed in to
 * `/api/v1` as business A's owner.
 *
 * Re-record on purpose (after an intended change):
 *   vendor/bin/pest --group=api-v1-contract-record
 */
const V1_CONTRACT_DIR = __DIR__.'/../Fixtures/api-v1-contract';

/**
 * @return list<array{method: string, uri: string, name: ?string, middleware: list<string>}>
 */
function v1ApiRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->map(fn (Route $route): array => [
            'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
            'uri' => $route->uri(),
            'name' => $route->getName(),
            'middleware' => array_values(array_map('strval', $route->gatherMiddleware())),
        ])
        ->sortBy(fn (array $route): string => $route['uri'].' '.$route['method'])
        ->values()
        ->all();
}

/**
 * Status and shape of sign-in and of every parameter-free GET on `/api/v1`.
 *
 * @return array<string, array{status: int, shape: mixed}>
 */
function v1ContractResponses(): array
{
    seedRolesAndPermissions();
    $fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();

    $record = fn ($response): array => [
        'status' => $response->status(),
        'shape' => ApiShape::of($response->json()),
    ];

    $signIn = test()->postJson('/api/v1/auth/sign-in', ['email' => $fixture->a->owner->email, 'password' => 'password', 'device_name' => 'Contract']);
    $responses = ['POST api/v1/auth/sign-in' => $record($signIn)];
    $headers = ['Authorization' => 'Bearer '.$signIn->json('data.token')];

    foreach (v1ApiRoutes() as $route) {
        if ($route['method'] !== 'GET' || str_contains($route['uri'], '{')) {
            continue;
        }
        app('auth')->forgetGuards();
        app(TenantContext::class)->forget();
        $responses["GET {$route['uri']}"] = $record(test()->withHeaders($headers)->getJson('/'.$route['uri']));
    }

    ksort($responses);

    return $responses;
}

it('keeps every recorded /api/v1 route', function () {
    $current = collect(v1ApiRoutes())->keyBy(fn (array $route): string => $route['method'].' '.$route['uri']);
    $recorded = json_decode(file_get_contents(V1_CONTRACT_DIR.'/routes.json'), true);

    $broken = collect($recorded)
        ->reject(fn (array $route): bool => $current->get($route['method'].' '.$route['uri']) == $route)
        ->map(fn (array $route): string => $route['method'].' '.$route['uri'].' ('.($current->has($route['method'].' '.$route['uri']) ? 'changed' : 'removed').')')
        ->values()
        ->all();

    expect($broken)->toBe([], '/api/v1 routes removed or changed: '.implode(', ', $broken));
});

it('keeps every recorded /api/v1 response shape', function () {
    $recorded = json_decode(file_get_contents(V1_CONTRACT_DIR.'/responses.json'), true);
    $current = v1ContractResponses();
    $breaks = [];

    foreach ($recorded as $endpoint => $expected) {
        if (! isset($current[$endpoint])) {
            $breaks[] = "{$endpoint}: no longer called";

            continue;
        }
        if ($current[$endpoint]['status'] !== $expected['status']) {
            $breaks[] = "{$endpoint}: status was {$expected['status']}, now {$current[$endpoint]['status']}";

            continue;
        }
        foreach (ApiShape::breaks($expected['shape'], $current[$endpoint]['shape']) as $break) {
            $breaks[] = "{$endpoint} {$break}";
        }
    }

    expect($breaks)->toBe([], implode("\n", $breaks));
});

it('records the /api/v1 contract', function () {
    if (! is_dir(V1_CONTRACT_DIR)) {
        mkdir(V1_CONTRACT_DIR, 0775, true);
    }

    file_put_contents(V1_CONTRACT_DIR.'/routes.json', json_encode(v1ApiRoutes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents(V1_CONTRACT_DIR.'/responses.json', json_encode(v1ContractResponses(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    expect(file_exists(V1_CONTRACT_DIR.'/responses.json'))->toBeTrue();
})->group('api-v1-contract-record');
