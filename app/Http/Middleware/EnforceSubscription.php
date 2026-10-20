<?php

namespace App\Http\Middleware;

use App\Exceptions\SubscriptionInactive;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only mode (Phase 4, decisions 4.6 and 4.9). While a business's
 * subscription has lapsed, or it has more shops than its plan covers, every
 * write on the web and both APIs is refused with `subscription_inactive`.
 * Reading, signing in and out, switching business, exporting data, paying,
 * closing the business and deleting shops stay available
 * (`config('billing.read_only_allowed')`).
 *
 * Runs after the business is resolved. Requests with no business or no
 * signed-in user (webhooks, the platform's own) are not judged here.
 */
class EnforceSubscription
{
    public function __construct(private TenantContext $tenant, private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->user() || ! $this->tenant->has()) {
            return $next($request);
        }

        $reason = $this->entitlements->readOnlyReason();

        if ($reason === null || $this->isAllowed($request)) {
            return $next($request);
        }

        throw new SubscriptionInactive($reason);
    }

    private function isAllowed(Request $request): bool
    {
        $name = (string) $request->route()?->getName();
        $path = $request->path();

        foreach ((array) config('billing.read_only_allowed', []) as $pattern) {
            if (($name !== '' && Str::is($pattern, $name)) || Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
