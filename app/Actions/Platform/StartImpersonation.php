<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\Impersonation;
use App\Models\User;
use App\Support\Platform\Impersonation as ImpersonationSession;
use App\Support\Platform\PlatformAudit;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A platform operator signs in as a business user (Phase 5, B3), for a fixed
 * time, with a reason. From then on the signed-in user IS that user: their
 * permissions in that business only, no Gate::before, no console, until the
 * impersonation ends (by hand, at its time limit, or on signing out). Logged
 * in `impersonations` and the audit log; a banner shows on every page.
 */
class StartImpersonation
{
    public function execute(Request $request, User $operator, Business $business, User $target, string $reason): Impersonation
    {
        if ($target->isPlatformOperator()) {
            throw new DomainException(__('A platform operator can\'t be impersonated.'));
        }

        if (! $business->isActive()) {
            throw new DomainException(__('Only someone in an active business can be impersonated.'));
        }

        $active = $target->memberships()->where('business_id', $business->id)->where('status', BusinessMembership::STATUS_ACTIVE)->exists();
        if (! $active) {
            throw new DomainException(__('This person isn\'t an active member of the business.'));
        }

        $impersonation = Impersonation::create([
            'operator_id' => $operator->id,
            'user_id' => $target->id,
            'business_id' => $business->id,
            'reason' => $reason,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(max(1, (int) config('platform.impersonation_minutes', 30))),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 480),
        ]);

        // Recorded as the operator, before the session becomes the user's.
        PlatformAudit::record('impersonation.started', $business, [
            'impersonation' => $impersonation->uuid,
            'user' => $target->uuid,
            'user_email' => $target->email,
            'reason' => $reason,
            'expires_at' => $impersonation->expires_at->toIso8601String(),
        ], AuditEvent::LOGIN);

        Auth::guard('web')->login($target);
        $request->session()->regenerate();
        $request->session()->put([
            ImpersonationSession::OPERATOR_KEY => $operator->id,
            ImpersonationSession::RECORD_KEY => $impersonation->id,
            ImpersonationSession::EXPIRES_KEY => $impersonation->expires_at->getTimestamp(),
            ResolveTenant::SESSION_KEY => $business->id,
        ]);

        return $impersonation;
    }
}
