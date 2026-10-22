<?php

namespace App\Http\Controllers;

use App\Actions\Business\CopyRoleTemplates;
use App\Actions\Business\SendBusinessInvite;
use App\Http\Requests\StoreBusinessInviteRequest;
use App\Models\BusinessInvite;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inviting people to the current business, and the pending invites (stage 3).
 */
class BusinessInviteController extends Controller
{
    public function __construct(private SendBusinessInvite $sendInvite) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BusinessInvite::class);

        /** @var User $user */
        $user = $request->user();

        return view('invites.index', [
            'invites' => BusinessInvite::query()->pending()->with(['role:id,name', 'inviter:id,name'])->latest()->get(),
            'roles' => Role::query()->active()->select('id', 'name')->orderBy('name')->get()
                ->reject(fn (Role $role): bool => $role->name === CopyRoleTemplates::OWNER_ROLE && ! $user->isBusinessOwner()),
            'shops' => Shop::query()->select('id', 'name')->get(),
        ]);
    }

    public function store(StoreBusinessInviteRequest $request): RedirectResponse
    {
        $this->authorize('create', BusinessInvite::class);

        $invite = $this->sendInvite->execute($request->validated(), $request->user());

        return redirect()->route('invites.index')
            ->with('success', __('Invitation sent to :email.', ['email' => $invite->email]));
    }

    public function resend(BusinessInvite $invite): RedirectResponse
    {
        $this->authorize('resend', $invite);

        $this->sendInvite->resend($invite);

        return redirect()->route('invites.index')
            ->with('success', __('Invitation sent again to :email.', ['email' => $invite->email]));
    }

    public function destroy(BusinessInvite $invite): RedirectResponse
    {
        $this->authorize('delete', $invite);

        $invite->update(['revoked_at' => now()]);

        return redirect()->route('invites.index')
            ->with('success', __('Invitation to :email withdrawn.', ['email' => $invite->email]));
    }
}
