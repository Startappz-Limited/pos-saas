<?php

namespace App\Actions\Platform;

use App\Enums\AuditEvent;
use App\Http\Middleware\ResolveTenant;
use App\Models\Impersonation;
use App\Models\User;
use App\Support\Platform\Impersonation as ImpersonationSession;
use App\Support\Platform\PlatformAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends an impersonation (Phase 5, B3): by hand, at its time limit, or when the
 * session signs out. The log is closed; unless signing out, the operator is
 * signed back in, and only then do their platform rights return. Audited as
 * the operator.
 */
class EndImpersonation
{
    /**
     * @return User|null the operator, signed back in (null when signing out)
     */
    public function execute(Request $request, string $reason = Impersonation::ENDED_MANUALLY): ?User
    {
        $session = $request->session();
        $record = Impersonation::query()->find($session->get(ImpersonationSession::RECORD_KEY));
        $operator = User::query()->find($session->get(ImpersonationSession::OPERATOR_KEY));

        if ($record !== null && $record->ended_at === null) {
            $record->update(['ended_at' => now(), 'end_reason' => $reason]);
        }

        // Signing out: recorded while the session is still the user's (the
        // row carries impersonated_by), and nobody is signed back in.
        if ($reason === Impersonation::SIGNED_OUT || $operator === null || ! $operator->isPlatformOperator()) {
            if ($record !== null) {
                PlatformAudit::record('impersonation.ended', $record->business, ['impersonation' => $record->uuid, 'end_reason' => $reason], AuditEvent::LOGOUT);
            }
            $this->forget($request);

            return null;
        }

        $this->forget($request);

        Auth::guard('web')->login($operator);
        $session->regenerate();

        if ($record !== null) {
            PlatformAudit::record('impersonation.ended', $record->business, [
                'impersonation' => $record->uuid,
                'end_reason' => $reason,
            ], AuditEvent::LOGOUT);
        }

        return $operator;
    }

    private function forget(Request $request): void
    {
        $request->session()->forget([ImpersonationSession::OPERATOR_KEY, ImpersonationSession::RECORD_KEY, ImpersonationSession::EXPIRES_KEY, ResolveTenant::SESSION_KEY]);
    }
}
