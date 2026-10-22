<?php

namespace App\Http\Middleware;

use App\Actions\Platform\EndImpersonation;
use App\Models\Impersonation;
use App\Support\Platform\Impersonation as ImpersonationSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an impersonation at its time limit (Phase 5, B3): the first request
 * after it signs the operator back in and opens the console, so nobody keeps
 * acting as a user longer than allowed.
 */
class HandleImpersonation
{
    public function __construct(private EndImpersonation $end) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! ImpersonationSession::active()) {
            return $next($request);
        }

        $expiresAt = (int) $request->session()->get(ImpersonationSession::EXPIRES_KEY, 0);

        if ($expiresAt > now()->getTimestamp()) {
            return $next($request);
        }

        $operator = $this->end->execute($request, Impersonation::EXPIRED);

        return $operator !== null
            ? redirect()->route('platform.dashboard')->with('warning', __('The impersonation reached its time limit and has ended.'))
            : redirect()->route('login');
    }
}
