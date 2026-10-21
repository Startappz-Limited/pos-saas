<?php

namespace App\Support\Logging;

use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Who and where, for log entries about errors and refusals: the signed-in
 * user, the business, and the route. The route is logged by name and pattern
 * (`invitations/{token}`), never as the actual URL: paths and query strings
 * can carry secrets (invite tokens, signatures). No request input is logged.
 */
final class RequestContext
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        try {
            $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();
            $route = $request?->route();

            return array_filter([
                'user_id' => auth()->id(),
                'business_id' => app(TenantContext::class)->id(),
                'route' => $route?->getName(),
                'route_uri' => $route?->uri(),
                'method' => $request?->method(),
                'ip' => $request?->ip(),
            ], fn ($value): bool => $value !== null);
        } catch (Throwable) {
            // Logging must never fail because the context couldn't be read.
            return [];
        }
    }
}
