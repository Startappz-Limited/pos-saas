<?php

namespace App\Http\Controllers;

use App\Actions\Business\CopyRoleTemplates;
use App\Actions\Business\SendBusinessInvite;
use App\Enums\Limit;
use App\Http\Requests\StoreBusinessInviteRequest;
use App\Models\BusinessInvite;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Rules\GrantableRole;
use App\Services\Billing\Entitlements;
use App\Services\Billing\PlanLimits;
use App\Support\Tenancy\TenantContext;
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
            'seatsUsed' => app(PlanLimits::class)->count(Limit::Staff, (int) app(TenantContext::class)->id()),
            'seatLimit' => app(Entitlements::class)->limit(Limit::Staff),
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

    /**
     * Changes the role a pending invite will give (Phase 6): the same rule as
     * sending one, so nobody is offered more than the sender holds.
     */
    public function changeRole(Request $request, BusinessInvite $invite): RedirectResponse
    {
        $this->authorize('changeRole', $invite);

        $data = $request->validate([
            'role_id' => ['required', 'integer', new GrantableRole($request->user())],
        ]);

        $invite->update(['role_id' => $data['role_id']]);

        return redirect()->route('invites.index')
            ->with('success', __('The role for :email is now :role.', ['email' => $invite->email, 'role' => $invite->fresh()->role->displayName()]));
    }

    public function destroy(BusinessInvite $invite): RedirectResponse
    {
        $this->authorize('delete', $invite);

        $invite->update(['revoked_at' => now()]);

        return redirect()->route('invites.index')
            ->with('success', __('Invitation to :email withdrawn.', ['email' => $invite->email]));
    }
}
