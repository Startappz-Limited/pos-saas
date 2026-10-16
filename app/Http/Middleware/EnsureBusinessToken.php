<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/api/v1` takes only tokens bound to a business (stage 3). A flat API token
 * (or a browser session) is refused with 403, never 401, so a client holding
 * one isn't signed out: it signs in to `/api/v1` for a token of its own.
 */
class EnsureBusinessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! is_numeric($token->getAttribute('business_id'))) {
            return response()->json([
                'success' => false,
                'message' => __('This endpoint needs a token from /api/v1 sign-in.'),
            ], 403);
        }

        return $next($request);
    }
}
