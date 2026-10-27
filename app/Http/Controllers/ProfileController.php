<?php

namespace App\Http\Controllers;

use App\Actions\User\ChangeUserEmail;
use App\Actions\User\DeactivateOwnAccount;
use App\Actions\User\ManageTillPin;
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
            'hasTillPin' => app(ManageTillPin::class)->has($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, ChangeUserEmail $change): RedirectResponse
    {
        $validated = $request->validated();

        // A new address starts unverified, with a fresh 24 hours, and the old
        // one is told (Phase 6).
        if (isset($validated['email'])) {
            $change->execute($request->user(), $validated['email']);
            unset($validated['email']);
        }

        $request->user()->fill($validated)->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Deactivate the user's own account (stage 3): the login is kept, not
     * deleted, and can no longer sign in.
     */
    public function destroy(Request $request, DeactivateOwnAccount $deactivate): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $deactivate->execute($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
