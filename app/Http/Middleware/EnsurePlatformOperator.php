<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Platform\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform console (`/platform`, Phase 5) is for platform operators only.
 * Anyone else, a business owner included, gets a 404: the console isn't
 * advertised. While an operator impersonates a user the signed-in user is
 * that user, and the console stays closed until impersonation ends.
 */
class EnsurePlatformOperator
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isPlatformOperator() && ! Impersonation::active(), 404);

        return $next($request);
    }
}
