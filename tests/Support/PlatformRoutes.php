<?php

namespace Tests\Support;

use App\Models\Role;
use Illuminate\Support\Facades\Route;
use RuntimeException;

/**
 * Every platform console route (Phase 5) with a URL to call, its parameters
 * filled from the two-business fixture (business B's records, so a leak would
 * show). Used by the console's access test and the isolation resource matrix,
 * so a new console route is checked on arrival.
 */
final class PlatformRoutes
{
    /**
     * @return list<array{0: string, 1: string, 2: string}> [method, url, route name]
     */
    public static function all(TwoBusinesses $fixture): array
    {
        $values = [
            'business' => fn () => $fixture->b->business,
            'user' => fn () => $fixture->b->cashier,
            'member' => fn () => $fixture->b->cashier->uuid,
            // The cashier template exists in every TwoBusinesses fixture, so B has a copy.
            'role' => fn () => sharedRole('cashier'),
            'copy' => fn () => Role::withoutGlobalScopes()->where('business_id', $fixture->b->business->id)->where('name', 'cashier')->firstOrFail()->uuid,
        ];
        $routes = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'platform.')) {
                continue;
            }

            $parameters = [];
            foreach ($route->parameterNames() as $name) {
                if (! isset($values[$name])) {
                    throw new RuntimeException("Add a value for {{$name}} (route {$route->getName()}) to tests/Support/PlatformRoutes.php.");
                }
                $parameters[$name] = $values[$name]();
            }

            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $routes[] = [strtolower($method), route($route->getName(), $parameters), $route->getName()];
        }

        return $routes;
    }
}
