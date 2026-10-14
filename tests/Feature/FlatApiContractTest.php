<?php

use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\LiveLikeDatasetSeeder;
use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * The flat /api contract the deployed Flutter app relies on (stage 0, step 7).
 * The SaaS work must never break it (plan 4.8): this test fails when a
 * recorded route is removed or changed, or a working endpoint's status
 * changes or a recorded response key disappears or changes type. Adding
 * routes or keys is allowed, and so is fixing an endpoint that was already
 * broken when recorded (status 400+; see "Known broken" in
 * docs/saas/PROGRESS.md).
 *
 * Recorded against the live-like dataset on MySQL, the production engine
 * family: raw report queries return numbers on SQLite where MySQL returns
 * strings, so shapes legitimately differ between engines.
 *
 * Re-record on purpose (after an intended change), on MySQL:
 *   vendor/bin/pest --configuration=phpunit.mysql.xml --group=api-contract-record
 *
 * (Not `php artisan test`: it always adds --configuration=phpunit.xml, and
 * PHPUnit 12 refuses a second --configuration.)
 */
const CONTRACT_DIR = __DIR__.'/../Fixtures/api-contract';

const CONTRACT_SCALE = 0.02;

/**
 * The value's shape: types instead of values; lists by their first element.
 */
function jsonShape(mixed $value): mixed
{
    // JSON has one number type: 0 encodes as an int and 12.5 as a float, and
    // the app reads both as numbers.
    if (is_int($value) || is_float($value)) {
        return 'number';
    }

    if (! is_array($value)) {
        return get_debug_type($value);
    }

    if (array_is_list($value)) {
        return $value === [] ? ['(empty list)'] : [jsonShape($value[0])];
    }

    $shape = [];
    foreach ($value as $key => $item) {
        $shape[$key] = jsonShape($item);
    }

    return $shape;
}

/**
 * Where $actual breaks the recorded $expected shape: a key gone, a type
 * changed, a list turned into something else. New keys are fine.
 *
 * @return list<string>
 */
function contractBreaks(mixed $expected, mixed $actual, string $path = '$'): array
{
    // null is compatible with any type: whether a field is empty depends on
    // the data (an empty report, a source without an icon), not the contract.
    if ($expected === 'null' || $actual === 'null') {
        return [];
    }

    if (is_string($expected)) {
        return $expected === $actual ? [] : ["{$path}: was {$expected}, now ".json_encode($actual)];
    }

    if (array_is_list($expected)) {
        if (! is_array($actual) || ! array_is_list($actual)) {
            return ["{$path}: was a list, now ".json_encode($actual)];
        }
        if ($expected === ['(empty list)'] || $actual === ['(empty list)']) {
            return [];
        }

        return contractBreaks($expected[0], $actual[0], "{$path}[0]");
    }

    if (! is_array($actual) || array_is_list($actual)) {
        return ["{$path}: was an object, now ".json_encode($actual)];
    }

    $breaks = [];
    foreach ($expected as $key => $shape) {
        $breaks = array_key_exists($key, $actual)
            ? [...$breaks, ...contractBreaks($shape, $actual[$key], "{$path}.{$key}")]
            : [...$breaks, "{$path}.{$key}: removed"];
    }

    return $breaks;
}

/**
 * @return list<array{method: string, uri: string, name: ?string, middleware: list<string>}>
 */
function flatApiRoutes(): array
{
    return collect(RouteFacade::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => str_starts_with($route->uri(), 'api/') && ! str_starts_with($route->uri(), 'api/v1/'))
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
 * Fills a GET route's {parameters} from the dataset, or null when one can't
 * be resolved.
 */
function contractUrl(Route $route): ?string
{
    $uri = $route->uri();
    $models = collect($route->signatureParameters(['subClass' => UrlRoutable::class]))
        ->mapWithKeys(fn (ReflectionParameter $parameter): array => [$parameter->getName() => $parameter->getType()?->getName()]);

    foreach ($route->parameterNames() as $name) {
        $class = $models[$name] ?? $models[Str::camel($name)] ?? null;
        $model = $class ? $class::query()->orderBy('id')->first() : null;

        if (! $model) {
            return null;
        }

        $field = $route->bindingFieldFor($name) ?? $model->getRouteKeyName();
        $uri = preg_replace('/\{'.preg_quote($name, '/').'(:[^}]+)?\??\}/', (string) $model->{$field}, $uri);
    }

    return '/'.$uri;
}

/**
 * Seeds the dataset, signs in as its owner and records every GET response,
 * plus signing in and creating a sale.
 *
 * @return array<string, array{status: int, shape: mixed}>
 */
function contractResponses(): array
{
    $seeder = app(LiveLikeDatasetSeeder::class);
    $seeder->scale = CONTRACT_SCALE;
    $seeder->run();
    // Act for the dataset's business (stage 1a), as its owner's requests do.
    app(TenantContext::class)->set($seeder->business);
    $owner = User::where('email', 'owner@dataset.test')->sole();
    // A failing endpoint is recorded by status and message only: debug
    // output (traces) changes all the time and is no part of the contract.
    $record = fn (TestResponse $response): array => [
        'status' => $response->status(),
        'shape' => $response->status() >= 400 ? ['message' => (string) $response->json('message')] : jsonShape($response->json()),
    ];
    $responses = [];

    // Signing in returns the token the app keeps.
    $responses['POST api/login'] = $record(test()->postJson('/api/login', [
        'email' => $owner->email, 'password' => 'password', 'device_name' => 'contract-test',
    ]));

    Sanctum::actingAs($owner, ['*']);

    foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), 'api/v1/')) {
            continue;
        }

        [$controller, $method] = array_pad(explode('@', (string) $route->getActionName()), 2, null);
        if ($method !== null && ! method_exists($controller, $method)) {
            $responses["GET {$route->uri()}"] = ['status' => 500, 'shape' => ['message' => "{$controller}::{$method} does not exist"]];

            continue;
        }

        $url = contractUrl($route);
        $responses["GET {$route->uri()}"] = $url === null
            ? ['status' => 0, 'shape' => '(no data to fill the route parameters)']
            : $record(test()->getJson($url));
    }

    // Creating a sale: the till's main write.
    $shop = Shop::orderBy('id')->first();
    $product = Product::where('shop_id', $shop->id)->where('has_variations', false)->orderBy('id')->first();
    $responses['POST api/sales'] = $record(test()->postJson('/api/sales', [
        'shop_id' => $shop->id,
        // The dataset business's own source: exists: rules are scoped to the
        // business since stage 1a.
        'source_id' => DB::table('sale_sources')->where('business_id', $shop->business_id)->orderBy('id')->value('id'),
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Contract Test',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => (float) $product->selling_price]],
    ]));

    ksort($responses);

    return $responses;
}

function onMySql(): void
{
    if (DB::getDriverName() !== 'mysql') {
        test()->markTestSkipped('Recorded and checked on MySQL (phpunit.mysql.xml): see the file header.');
    }
}

it('keeps every recorded flat API route', function () {
    onMySql();
    $recorded = json_decode(file_get_contents(CONTRACT_DIR.'/routes.json'), true);
    $current = collect(flatApiRoutes())->keyBy(fn (array $route): string => $route['method'].' '.$route['uri']);

    $broken = collect($recorded)
        ->reject(fn (array $route): bool => $current->get($route['method'].' '.$route['uri']) === $route)
        ->map(fn (array $route): string => $route['method'].' '.$route['uri'].' ('.($current->has($route['method'].' '.$route['uri']) ? 'changed' : 'removed').')')
        ->values()
        ->all();

    expect($broken)->toBe([], 'Flat API routes removed or changed: '.implode(', ', $broken));
});

it('keeps every recorded flat API response shape', function () {
    onMySql();
    $recorded = json_decode(file_get_contents(CONTRACT_DIR.'/responses.json'), true);
    $current = contractResponses();
    $breaks = [];

    foreach ($recorded as $endpoint => $expected) {
        if (! isset($current[$endpoint])) {
            $breaks[] = "{$endpoint}: no longer called";

            continue;
        }
        // Endpoints already broken when recorded (status 400+) may be fixed;
        // working ones must keep their status and shape.
        if ($expected['status'] >= 400) {
            continue;
        }
        if ($current[$endpoint]['status'] !== $expected['status']) {
            $breaks[] = "{$endpoint}: status was {$expected['status']}, now {$current[$endpoint]['status']}";

            continue;
        }
        foreach (contractBreaks($expected['shape'], $current[$endpoint]['shape']) as $break) {
            $breaks[] = "{$endpoint} {$break}";
        }
    }

    expect($breaks)->toBe([], "Flat API responses changed:\n".implode("\n", $breaks));
});

it('records the flat API contract', function () {
    onMySql();
    @mkdir(CONTRACT_DIR, 0755, true);

    file_put_contents(CONTRACT_DIR.'/routes.json', json_encode(flatApiRoutes(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    file_put_contents(CONTRACT_DIR.'/responses.json', json_encode(contractResponses(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    expect(file_exists(CONTRACT_DIR.'/responses.json'))->toBeTrue();
})->group('api-contract-record');
