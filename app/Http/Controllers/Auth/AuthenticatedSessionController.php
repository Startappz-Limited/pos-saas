<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Platform\EndImpersonation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Impersonation as ImpersonationRecord;
use App\Support\Platform\Impersonation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // A platform operator belongs to no business: the console is home (Phase 5).
        if ($request->user()->isPlatformOperator()) {
            return redirect()->intended(route('platform.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Signing out ends an impersonation too, and closes its log (Phase 5).
        if (Impersonation::active()) {
            app(EndImpersonation::class)->execute($request, ImpersonationRecord::SIGNED_OUT);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
