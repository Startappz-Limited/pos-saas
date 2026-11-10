<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Http\Responses\ApiV1;
use App\Models\Business;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/api/v1` takes only tokens bound to a business (stage 3). A flat API token
 * (or a browser session) is refused with 403, never 401, so a client holding
 * one isn't signed out: it signs in to `/api/v1` for a token of its own.
 *
 * A token bound to a business it can no longer work in (stage 10a, step 1)
 * leaves `TenantContext` unset (`ResolveTenant`): refused with
 * `business_suspended` when that business was suspended, else
 * `no_active_business` (closing, pending verification, or the membership is
 * inactive or gone) — on every call but the ones that get the app unstuck:
 * signing out, listing the businesses the token's owner can still reach, and
 * switching to one of them. Without that carve-out a token that just lost
 * its business could never discover or move to another it still has, and
 * would be stuck until a fresh sign-in.
 */
class EnsureBusinessToken
{
    /** Reachable even with no business resolved, so a token can recover. */
    private const EXEMPT_ROUTES = ['api.v1.auth.sign-out', 'api.v1.businesses.index', 'api.v1.businesses.switch'];

    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! is_numeric($boundTo = $token->getAttribute('business_id'))) {
            return response()->json([
                'success' => false,
                'code' => ApiErrorCode::BusinessTokenRequired->value,
                'message' => __('This endpoint needs a token from /api/v1 sign-in.'),
            ], 403);
        }

        if ($this->tenant->has() || in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        $business = Business::query()->find((int) $boundTo);

        if ($business?->status === Business::STATUS_SUSPENDED) {
            return ApiV1::error(ApiErrorCode::BusinessSuspended, __('This business is suspended. Nobody can work in it until it is reactivated.'), 403);
        }

        return ApiV1::error(ApiErrorCode::NoActiveBusiness, __('You are not a member of any active business.'), 403);
    }
}
