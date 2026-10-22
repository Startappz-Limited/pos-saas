<?php

namespace App\Http\Controllers\Auth;

use App\Actions\User\ChangeUserEmail;
use App\Http\Controllers\Controller;
use App\Rules\RealEmail;
use App\Rules\UniqueLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Fixing a mistyped address from the verification page (Phase 6). Available
 * while the grace period has run out, so a lost mailbox is not a lockout. The
 * current password is asked for, since this changes where the account's mail
 * and reset links go.
 */
class VerificationEmailController extends Controller
{
    public function update(Request $request, ChangeUserEmail $change): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('profile.edit');
        }

        $data = $request->validateWithBag('changeEmail', [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', new RealEmail, new UniqueLogin($user->id)],
            'current_password' => ['required', 'current_password'],
        ]);

        $change->execute($user, $data['email']);

        return redirect()->route('verification.notice')->with('status', 'verification-email-changed');
    }
}
