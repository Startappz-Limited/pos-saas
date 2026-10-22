<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Business\RegisterBusiness;
use App\Http\Controllers\Controller;
use App\Rules\RealEmail;
use App\Rules\StrongPassword;
use App\Rules\UniqueLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request: a new login, its business and
     * its owner membership (stage 3). The business opens, and its trial starts,
     * once the email is verified.
     *
     * @throws ValidationException
     */
    public function store(Request $request, RegisterBusiness $registerBusiness): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new RealEmail, new UniqueLogin],
            'password' => ['required', 'confirmed', new StrongPassword],
        ]);

        $user = $registerBusiness->execute($validated);

        event(new Registered($user));

        // Users don't implement MustVerifyEmail (existing logins aren't asked to
        // verify), so the link is sent here rather than by the Registered listener.
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect(route('verification.notice', absolute: false));
    }
}
