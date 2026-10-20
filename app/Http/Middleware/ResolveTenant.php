<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the business a signed-in request acts for (decision 4.3).
 *
 * On the web: the business chosen for the session, re-checked against the
 * user's memberships on every request, otherwise the user's default business,
 * otherwise their only usable business (stage 3). With several and no usable
 * default, none is chosen and the user picks one (EnsureActiveBusiness). An
 * inactive membership or a suspended or closing business is never chosen.
 * With an `/api/v1` token: the business the token is bound to, while the user
 * can still work in it (stage 3). With a flat API token: the user's default
 * business, which is how legacy flat API tokens keep working (4.8). A user with no usable business gets no
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
        $token = $user->currentAccessToken();

        // (Sanctum::actingAs's stand-in token answers false: it isn't bound.)
        $boundTo = $token instanceof PersonalAccessToken ? $token->getAttribute('business_id') : null;

        if (is_numeric($boundTo)) {
            return $user->canEnterBusiness((int) $boundTo) ? (int) $boundTo : null;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $chosen = $session?->get(self::SESSION_KEY);

        if ($chosen !== null && $user->canEnterBusiness((int) $chosen)) {
            return (int) $chosen;
        }

        $session?->forget(self::SESSION_KEY);

        $businessId = $user->businessToOpen();

        if ($businessId !== null && $businessId !== $user->default_business_id) {
            $session?->put(self::SESSION_KEY, $businessId);
        }

        return $businessId;
    }
}
