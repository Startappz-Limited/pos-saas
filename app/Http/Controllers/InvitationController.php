<?php

namespace App\Http\Controllers;

use App\Actions\Business\AcceptBusinessInvite;
use App\Http\Middleware\ResolveTenant;
use App\Models\BusinessInvite;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The invite link (stage 3). A signed-in person with the invited email
 * accepts it; an email that already has a login signs in first; a new email
 * creates its login here. Any other signed-in login is told the invite isn't
 * theirs.
 */
class InvitationController extends Controller
{
    public function __construct(private AcceptBusinessInvite $acceptInvite) {}

    public function show(Request $request, string $token): View
    {
        $invite = BusinessInvite::findByToken($token);

        if ($invite === null || ! $invite->isPending()) {
            return view('invitations.invalid');
        }

        $user = $request->user();

        return view('invitations.show', [
            // The role is the business's own: read it in that business.
            'invite' => app(TenantContext::class)->run($invite->business_id, fn () => $invite->load('business:id,name', 'role:id,name')),
            'token' => $token,
            'user' => $user,
            'hasAccount' => User::query()->where('email', $invite->email)->exists(),
        ]);
    }

    /**
     * Sign in, then come back to the invite.
     */
    public function signIn(Request $request, string $token): RedirectResponse
    {
        $request->session()->put('url.intended', route('invitations.show', $token));

        return redirect()->route('login');
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invite = $this->pendingInvite($token);

        /** @var User $user */
        $user = $request->user();

        return $this->acceptFor($request, $invite, $user);
    }

    /**
     * A new login for an invited email that has none.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invite = $this->pendingInvite($token);

        abort_if(User::query()->where('email', $invite->email)->exists(), 409);

        // Before a login is made for it: a switched-off role or a read-only
        // business means there is nothing to join.
        try {
            $this->acceptInvite->assertAcceptable($invite);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('invitations.show', $token)->with('error', __($exception->getMessage()));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // No confirmation field: the page has a show/hide toggle instead.
            'password' => ['required', new StrongPassword([$invite->email])],
        ]);

        // The invitee's own name wins over the one the inviter typed.
        $name = trim($validated['name']);

        $user = User::create([
            'name' => $name,
            'email' => $invite->email,
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->acceptFor($request, $invite, $user);
    }

    private function acceptFor(Request $request, BusinessInvite $invite, User $user): RedirectResponse
    {
        try {
            $this->acceptInvite->execute($invite, $user);
        } catch (InvalidArgumentException $exception) {
            return redirect()->route('invitations.show', $request->route('token'))->with('error', __($exception->getMessage()));
        }

        $request->session()->put(ResolveTenant::SESSION_KEY, $invite->business_id);

        return redirect()->route('dashboard')
            ->with('success', __('Welcome to :business.', ['business' => $invite->business->name]));
    }

    private function pendingInvite(string $token): BusinessInvite
    {
        $invite = BusinessInvite::findByToken($token);

        abort_if($invite === null || ! $invite->isPending(), 404);

        return $invite;
    }
}
