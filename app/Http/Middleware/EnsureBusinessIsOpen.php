<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a business is closing (the grace period before its data is deleted)
 * only its owner can sign in, and only to the closing page: to download the
 * final export or cancel. Staff are signed out (web) or refused with
 * 403 business_closing (API). Nothing is revoked, so cancelling the closure
 * lets everyone straight back in.
 */
class EnsureBusinessIsOpen
{
    /**
     * Routes the owner can still reach while the business is closing.
     *
     * @var array<int, string>
     */
    private const OWNER_ROUTES = [
        'business.closing',
        'business.closing.cancel',
        'business.exports.download',
        'logout',
        'api.logout',
        'api.user',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperAdmin() || ! $user->business?->isClosing()) {
            return $next($request);
        }

        $isOwner = $user->isBusinessOwner();

        if ($isOwner && $request->routeIs(self::OWNER_ROUTES)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            if ($request->routeIs('api.logout')) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'code' => 'business_closing',
                'message' => $isOwner
                    ? __('This business is closing. Sign in on the web to download your data or cancel the closure.')
                    : __('This business is closing and can no longer be used. Contact the business owner.'),
            ], 403);
        }

        if ($isOwner) {
            return redirect()->route('business.closing');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('This business is closing and can no longer be used. Contact the business owner.'),
        ]);
    }
}
