<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff can only work once an admin has linked them to a shop. Until then the
 * web UI shows the "contact your administrator" page and the API returns 403.
 *
 * Super-admins (platform) and admins (shop owners) are never blocked: an admin
 * with no shop yet just sees empty lists until they create one.
 */
class EnsureUserIsLinkedToShop
{
    /**
     * Routes an unlinked user may still reach: the notice itself, signing out,
     * and their own profile.
     *
     * @var array<int, string>
     */
    private const ALLOWED_ROUTES = [
        'no-shop',
        'logout',
        'profile.*',
        'api.logout',
        'api.user',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            $user->ensureBusiness();

            return $next($request);
        }

        if ($user->accessibleShopIds()->isNotEmpty() || $request->routeIs(self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => 'shop_not_linked',
                'message' => __('Your account is not linked to a shop yet. Contact your administrator to link you to a shop.'),
            ], 403);
        }

        return redirect()->route('no-shop');
    }
}
