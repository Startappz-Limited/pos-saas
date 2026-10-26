<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Tests\Support\TwoBusinesses;

/**
 * The `/api/v1` OpenAPI document (Scramble, Phase 7 step 1): it documents
 * exactly the `/api/v1` routes, no more and no fewer, and only platform
 * operators can read it outside local.
 */

/**
 * Every `/api/v1` route as "METHOD /path-under-v1", parameters named `{x}`.
 *
 * @return list<string>
 */
function openApiV1Routes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/'))
        ->flatMap(fn (Route $route) => collect(array_diff($route->methods(), ['HEAD']))
            ->map(fn (string $method): string => $method.' /'.preg_replace('/\{([^}:]+)(:[^}]+)?\}/', '{$1}', Str::after($route->uri(), 'api/v1/'))))
        ->sort()->values()->all();
}

it('documents exactly the /api/v1 routes', function () {
    $document = $this->actingAs(platformOperator())->getJson('/docs/api.json')->assertOk()->json();

    $documented = collect($document['paths'])
        ->flatMap(fn (array $operations, string $path) => collect(array_keys($operations))
            ->filter(fn (string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true))
            ->map(fn (string $method): string => strtoupper($method).' '.$path))
        ->sort()->values()->all();

    expect($documented)->toBe(openApiV1Routes())
        ->and($document['servers'][0]['url'])->toEndWith('/api/v1');
});

it('shows the document only to platform operators outside local', function () {
    seedRolesAndPermissions();
    $fixture = TwoBusinesses::create();

    $this->getJson('/docs/api.json')->assertForbidden();
    $this->actingAs($fixture->a->owner)->getJson('/docs/api.json')->assertForbidden();
});
