<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the business a signed-in request acts for (decision 4.3).
 *
 * On the web: the business chosen for the session, re-checked against the
 * user's memberships on every request, otherwise the user's default business.
 * With an API token: the user's default business, which is how legacy flat
 * API tokens keep working (4.8). A user with no usable business gets no
 * business, so tenant data stays out of reach.
 *
 * Runs after authentication (see bootstrap/app.php).
 */
class ResolveTenant
{
    public const SESSION_KEY = 'tenant.business_id';

    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $this->tenant->set($this->businessFor($request, $user));
        }

        return $next($request);
    }

    private function businessFor(Request $request, User $user): ?int
    {
        $session = $request->hasSession() ? $request->session() : null;
        $chosen = $session?->get(self::SESSION_KEY);

        if ($chosen !== null && $user->canEnterBusiness((int) $chosen)) {
            return (int) $chosen;
        }

        $session?->forget(self::SESSION_KEY);

        return $user->canEnterBusiness($user->default_business_id) ? $user->default_business_id : null;
    }
}
