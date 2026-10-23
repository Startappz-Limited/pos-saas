<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Email verification with a grace period (Phase 6). A new owner may use the
 * system for 24 hours (`users.verification_deadline_at`); after that, with the
 * email still unverified, everything is refused except verifying, resending the
 * link, changing the email and signing out
 * (`config('security.verification_allowed')`). The block covers the web, the
 * flat API and `/api/v1` (sync included), answering 403 with
 * `email_verification_required`. Verifying restores access at once.
 */
class EnforceEmailVerification
{
    public const CODE = 'email_verification_required';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->mustVerifyEmailNow() || $this->isAllowed($request)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'code' => self::CODE,
                'message' => __('Verify your email address to keep using your account.'),
            ], 403);
        }

        return redirect()->route('verification.notice');
    }

    private function isAllowed(Request $request): bool
    {
        $name = (string) $request->route()?->getName();
        $path = $request->path();

        foreach ((array) config('security.verification_allowed', []) as $pattern) {
            if (($name !== '' && Str::is($pattern, $name)) || Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
