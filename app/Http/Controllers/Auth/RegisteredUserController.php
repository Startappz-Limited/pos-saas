<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Business\RegisterBusiness;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Rules\RealEmail;
use App\Rules\StrongPassword;
use App\Rules\UniqueLogin;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register', [
            'freeTrial' => Plan::query()->where('is_active', true)->where('is_trial_plan', true)->first(),
            'tiers' => Plan::query()->tiers()->get(),
        ]);
    }

    /**
     * Handle an incoming registration request: a new login, its business, its
     * first shop and a 14-day trial (the Free Trial or the tier picked), all
     * usable at once. The owner has 24 hours to verify their email (Phase 6).
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
            'plan' => ['nullable', 'string', Rule::exists('plans', 'key')->where('is_active', true)],
            'shop_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $registerBusiness->execute($validated);

        event(new Registered($user));

        // Users don't implement MustVerifyEmail (existing logins aren't asked to
        // verify), so the link is sent here rather than by the Registered listener.
        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
