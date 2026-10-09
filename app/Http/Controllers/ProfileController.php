<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Deactivate the signed-in user's own account. Accounts are not deleted
     * from here: deletion erases a person's data and is reserved for the
     * business owner (Users screen). The owner and super-admins cannot
     * deactivate themselves: a business must not lose its owner this way,
     * and a super-admin is managed by another super-admin.
     */
    public function deactivate(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeactivation', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->isSuperAdmin() || $user->isBusinessOwner()) {
            return Redirect::route('profile.edit')
                ->withErrors(['password' => __('This account cannot be deactivated from here.')], 'userDeactivation');
        }

        // Saving the status also ends every session and app token (User::booted)
        $user->update(['status' => UserStatus::INACTIVE]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::route('login')->with('status', __('Your account has been deactivated.'));
    }
}
