<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that work on a business need one (stage 3). A signed-in user with no
 * usable business is sent to verify their email when their signup is still
 * waiting for it, and otherwise shown that they have no business, instead of
 * reaching tenant code with none set.
 */
class EnsureActiveBusiness
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $this->tenant->has()) {
            return $next($request);
        }

        if (! $user->hasVerifiedEmail() && $this->awaitsVerification($user)) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => __('Verify your email address to start using your business.')], 403)
                : redirect()->route('verification.notice');
        }

        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => __('You are not a member of any active business.')], 403)
            : response()->view('businesses.none', status: 403);
    }

    private function awaitsVerification(User $user): bool
    {
        return $user->memberships()
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_PENDING_VERIFICATION))
            ->exists();
    }
}
