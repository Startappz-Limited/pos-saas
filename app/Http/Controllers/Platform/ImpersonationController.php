<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\EndImpersonation;
use App\Actions\Platform\StartImpersonation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StartImpersonationRequest;
use App\Models\Business;
use App\Models\User;
use App\Support\Platform\Impersonation;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Impersonation (Phase 5, B3). Starting is a console action (operators only);
 * ending is open to the impersonated session, since by then the signed-in user
 * is the business user.
 */
class ImpersonationController extends Controller
{
    /**
     * @param  string  $member  the member's uuid (User route binding only finds
     *                          members of the current business, and an
     *                          operator is in none)
     */
    public function start(StartImpersonationRequest $request, Business $business, string $member, StartImpersonation $start): RedirectResponse
    {
        $target = User::query()->where('uuid', $member)->first();
        abort_if($target === null, 404);

        try {
            $start->execute($request, $request->user(), $business, $target, $request->validated('reason'));
        } catch (DomainException $e) {
            return redirect()->route('platform.businesses.show', $business)->with('error', $e->getMessage());
        }

        return redirect()->route('dashboard');
    }

    public function end(Request $request, EndImpersonation $end): RedirectResponse
    {
        abort_unless(Impersonation::active(), 404);

        $operator = $end->execute($request);

        return $operator !== null
            ? redirect()->route('platform.dashboard')->with('success', __('Impersonation ended. You are back in the platform console.'))
            : redirect()->route('login');
    }
}
