<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveTenant;
use App\Models\Business;
use App\Models\BusinessClosure;
use App\Models\BusinessExport;
use App\Models\BusinessMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The business picker, the switcher and the default business (decision 4.4,
 * stage 3). Only a business the user can work in now can be opened or made the
 * default; any other is a 404, so another business's existence isn't revealed.
 */
class BusinessSwitchController extends Controller
{
    /**
     * The picker: every business the user can work in.
     */
    public function index(Request $request, TenantContext $tenant): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('businesses.index', [
            'businesses' => $user->usableBusinesses(),
            'closing' => $this->closingOwnedBy($user, $tenant),
            'currentId' => $tenant->id(),
            'defaultId' => $user->default_business_id,
        ]);
    }

    /**
     * Opens a business for this session. With no usable default, the choice
     * becomes the default.
     */
    public function switch(Request $request, Business $business): RedirectResponse
    {
        $user = $this->member($request, $business);

        $request->session()->put(ResolveTenant::SESSION_KEY, $business->id);

        if (! $user->canEnterBusiness($user->default_business_id)) {
            $user->forceFill(['default_business_id' => $business->id])->save();
        }

        return redirect()->route('dashboard')
            ->with('success', __('Now working in :business.', ['business' => $business->name]));
    }

    /**
     * Makes a business the one a sign-in opens.
     */
    public function makeDefault(Request $request, Business $business): RedirectResponse
    {
        $user = $this->member($request, $business);

        $user->forceFill(['default_business_id' => $business->id])->save();

        return back()->with('success', __(':business now opens when you sign in.', ['business' => $business->name]));
    }

    /**
     * Closing businesses the user owns, with their closure: they can't be
     * opened, but an owner may cancel the closure or download the export.
     *
     * @return Collection<int, array{business: Business, closure: ?BusinessClosure, export: ?BusinessExport}>
     */
    private function closingOwnedBy(User $user, TenantContext $tenant): Collection
    {
        return $user->businesses()
            ->wherePivot('status', BusinessMembership::STATUS_ACTIVE)
            ->where('businesses.status', Business::STATUS_CLOSING)
            ->get()
            ->filter(fn (Business $business): bool => $tenant->run($business, fn (): bool => $user->fresh()->isBusinessOwner()))
            ->map(fn (Business $business): array => [
                'business' => $business,
                'closure' => BusinessClosure::query()->open()->where('business_id', $business->id)->latest('id')->first(),
                'export' => $tenant->run($business, fn () => BusinessExport::query()->where('status', BusinessExport::STATUS_READY)->latest('id')->first()),
            ])
            ->values();
    }

    private function member(Request $request, Business $business): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canEnterBusiness($business), 404);

        return $user;
    }
}
