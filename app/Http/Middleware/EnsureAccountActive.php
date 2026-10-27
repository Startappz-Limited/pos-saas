<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Account status (stage 3): a login whose account isn't active is signed out
 * on the web and refused on the API, on its next request. (Status in one
 * business is the membership's, enforced by ResolveTenant.) The API answers
 * 403, never 401, so the app shows the message rather than clearing a
 * shared till's sign-in on its own.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->isActive()) {
            return $next($request);
        }

        $message = __('Your account is not active. Please contact an administrator.');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'code' => ApiErrorCode::AccountInactive->value, 'message' => $message], 403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
