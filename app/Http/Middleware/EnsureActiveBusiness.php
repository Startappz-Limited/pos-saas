<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Models\Business;
use App\Models\User;
use App\Support\Platform\Impersonation;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that work on a business need one (stage 3). A signed-in user with no
 * business chosen is sent to verify their email when their signup is still
 * waiting for it, to the business picker when they have several usable
 * businesses and no usable default, and otherwise shown that they have no
 * business, instead of reaching tenant code with none set.
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

        // A platform operator works in the console, outside every business (Phase 5).
        if ($user->isPlatformOperator() && ! Impersonation::active()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'code' => ApiErrorCode::PlatformOperator->value, 'message' => __('Platform operators work in the platform console.')], 403)
                : redirect()->route('platform.dashboard');
        }

        if (! $user->hasVerifiedEmail() && $this->awaitsVerification($user)) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'code' => ApiErrorCode::EmailVerificationRequired->value, 'message' => __('Verify your email address to start using your business.')], 403)
                : redirect()->route('verification.notice');
        }

        // Several to choose from, or a closing business they may reopen.
        if ($user->usableBusinesses()->isNotEmpty() || $this->ownsClosingBusiness($user)) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'code' => ApiErrorCode::BusinessRequired->value, 'message' => __('Choose a business to work in.')], 409)
                : redirect()->guest(route('businesses.index'));
        }

        // Phase 5: say so when the business was suspended by the platform.
        if ($this->belongsToSuspendedBusiness($user)) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'code' => ApiErrorCode::BusinessSuspended->value, 'message' => __('This business is suspended. Nobody can work in it until it is reactivated.')], 403)
                : response()->view('businesses.none', ['suspended' => true], 403);
        }

        return $request->expectsJson()
            ? response()->json(['success' => false, 'code' => ApiErrorCode::NoActiveBusiness->value, 'message' => __('You are not a member of any active business.')], 403)
            : response()->view('businesses.none', status: 403);
    }

    /**
     * Whether the user is an owner of a closing business (the picker lets
     * them cancel the closure or download its final export).
     */
    private function ownsClosingBusiness(User $user): bool
    {
        return $user->memberships()->where('status', 'active')
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_CLOSING))
            ->pluck('business_id')
            ->contains(fn (int $businessId): bool => $this->tenant->run($businessId, fn (): bool => $user->fresh()->isBusinessOwner()));
    }

    private function belongsToSuspendedBusiness(User $user): bool
    {
        return $user->memberships()->where('status', 'active')
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_SUSPENDED))
            ->exists();
    }

    private function awaitsVerification(User $user): bool
    {
        return $user->memberships()
            ->whereHas('business', fn ($query) => $query->where('status', Business::STATUS_PENDING_VERIFICATION))
            ->exists();
    }
}
