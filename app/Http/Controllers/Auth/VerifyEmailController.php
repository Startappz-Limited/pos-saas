<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Signup\VerificationLink;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        // Only the newest link works: a resend replaces the older ones.
        if (! VerificationLink::isCurrent($request->user(), $request->query('n'))) {
            return redirect()->route('verification.notice')
                ->with('error', __('That link has been replaced by a newer one. Use the latest email we sent you, or send a new link.'));
        }

        if ($request->user()->markEmailAsVerified()) {
            VerificationLink::forget($request->user());
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
