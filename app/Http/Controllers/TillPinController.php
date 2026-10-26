<?php

namespace App\Http\Controllers;

use App\Actions\User\ManageTillPin;
use App\Http\Requests\SetTillPinRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Till PINs on the web (Phase 7): a person sets their own on the profile
 * page; whoever may edit a person can reset theirs from the person's page.
 */
class TillPinController extends Controller
{
    public function update(SetTillPinRequest $request, ManageTillPin $pins): RedirectResponse
    {
        $pins->set($request->user(), $request->validated('pin'));

        return redirect()->route('profile.edit')->with('success', __('Till PIN saved. Use it to sign in on a till that is offline.'));
    }

    public function reset(User $user, ManageTillPin $pins): RedirectResponse
    {
        $this->authorize('update', $user);

        return back()->with('success', $pins->reset($user)
            ? __(':name\'s till PIN was reset. They need to set a new one before signing in on an offline till.', ['name' => $user->name])
            : __(':name has no till PIN to reset.', ['name' => $user->name]));
    }
}
