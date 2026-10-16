<?php

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Canaries;
use Tests\Support\TwoBusinesses;

/**
 * The canary crawl (Phase 2): every parameter-free GET route behind sign-in,
 * web and flat API, requested as one business, must not show the other
 * business's markers (its names and record uuids). Run as each kind of viewer:
 * the owner, a cashier limited to one shop, and a member of both businesses.
 *
 * A route that errors fails the crawl too, unless it is listed below as broken
 * already (each with its reason), so a broken page can't hide a leak.
 */

/** Routes that fail before tenancy is involved: missing methods and views, already on main. */
const BROKEN_ROUTES = [
    '/inventory-snapshots/report/valuation' => 'InventorySnapshotController::valuationReport does not exist',
    '/stock-movements/additions/list' => 'StockMovementController::additions does not exist',
    '/stock-movements/deductions/list' => 'StockMovementController::deductions does not exist',
    '/stock-movements/stats/report' => 'StockMovementController::stats does not exist',
    '/customers/credit-alerts/list' => 'view customers.credit-alerts does not exist',
    '/sales/pending/list' => 'view sales.pending does not exist',
    '/sales/completed/list' => 'view sales.completed does not exist',
    '/sales/report/daily' => 'view sales.daily-report does not exist',
    '/sales/report/profit' => 'view sales.profit-report does not exist',
    '/api/inventory' => 'Api\InventoryController::index does not exist',
    '/api/inventory/low-stock' => 'Api\InventoryController::lowStock does not exist',
];

/** Broken on SQLite only: MySQL's MONTH(); checked on MySQL. */
const BROKEN_ON_SQLITE = [
    '/expenses/report/summary' => 'MONTH() is MySQL-only',
];

beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }

    // The real permissions and role templates, so each viewer has the access
    // a real install gives them.
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->markers = [
        'a' => Canaries::plant($this->fixture->a, 'Alpha'),
        'b' => Canaries::plant($this->fixture->b, 'Bravo'),
    ];
    app(TenantContext::class)->forget();
});

/**
 * Parameter-free GET routes behind sign-in: web (auth) or flat API (auth:sanctum).
 *
 * @return list<string>
 */
function crawlableUris(string $kind): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'))
        ->filter(fn (RoutingRoute $route) => $kind === 'api'
            ? str_starts_with($route->uri(), 'api/') && in_array('auth:sanctum', $route->gatherMiddleware(), true)
            : ! str_starts_with($route->uri(), 'api/') && in_array('auth', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route) => '/'.ltrim($route->uri(), '/'))
        ->reject(fn (string $uri) => array_key_exists($uri, BROKEN_ROUTES)
            || (DB::getDriverName() === 'sqlite' && array_key_exists($uri, BROKEN_ON_SQLITE)))
        ->values()->all();
}

/**
 * Crawls every route as $viewer and returns what went wrong: a leaked
 * marker, or a server error.
 *
 * @param  list<string>  $forbidden
 * @return list<string>
 */
function crawlAs(User $viewer, array $forbidden): array
{
    $problems = [];

    foreach (['web', 'api'] as $kind) {
        foreach (crawlableUris($kind) as $uri) {
            app(TenantContext::class)->forget();
            auth()->forgetGuards();
            $response = $kind === 'api'
                ? (function () use ($viewer, $uri) {
                    Sanctum::actingAs($viewer, ['*']);

                    return test()->getJson($uri);
                })()
                : test()->actingAs($viewer)->get($uri);

            if ($response->getStatusCode() >= 500) {
                $problems[] = "{$uri}: {$response->getStatusCode()} ".substr((string) $response->exception?->getMessage(), 0, 160);

                continue;
            }

            $body = (string) $response->getContent();
            foreach ($forbidden as $marker) {
                if (str_contains($body, $marker)) {
                    $problems[] = "{$uri}: shows {$marker}";
                }
            }
        }
    }

    return $problems;
}

it('shows business A nothing of business B', function (string $viewer) {
    $user = match ($viewer) {
        'owner' => $this->fixture->a->owner,
        'cashier' => $this->fixture->a->cashier,
        'member of both' => $this->fixture->both,
    };

    expect(crawlAs($user, $this->markers['b']))->toBe([]);
})->with(['owner', 'cashier', 'member of both']);

it('shows business B nothing of business A', function () {
    expect(crawlAs($this->fixture->b->owner, $this->markers['a']))->toBe([]);
});

it('crawls every signed-in GET route without parameters', function () {
    // A guard on the crawl itself: if route registration changes so that the
    // crawl finds almost nothing, the isolation checks above prove nothing.
    expect(count(crawlableUris('web')))->toBeGreaterThan(90)
        ->and(count(crawlableUris('api')))->toBeGreaterThan(20);
});
