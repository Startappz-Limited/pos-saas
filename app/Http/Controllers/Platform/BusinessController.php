<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\ChangeBusinessPlan;
use App\Actions\Platform\ExtendTrial;
use App\Actions\Platform\ReactivateBusiness;
use App\Actions\Platform\SuspendBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ChangeBusinessPlanRequest;
use App\Http\Requests\Platform\ExtendTrialRequest;
use App\Http\Requests\Platform\SuspendBusinessRequest;
use App\Models\Business;
use App\Models\Plan;
use App\Models\PlatformPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Support\Platform\BusinessDirectory;
use App\Support\Platform\PlatformAudit;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Businesses in the platform console (Phase 5, B1): the list and a business's
 * page, suspend and reactivate, change plan, extend trial. Every action,
 * reads included, is audited. Access is the route group's.
 */
class BusinessController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->value() ?: null;
        $status = $request->string('status')->value() ?: null;

        $businesses = BusinessDirectory::query($search, $status)->orderBy('businesses.name')->paginate(25)->withQueryString();

        PlatformAudit::record('businesses.listed', null, array_filter(['search' => $search, 'status' => $status, 'page' => $businesses->currentPage()]));

        return view('platform.businesses.index', [
            'businesses' => $businesses,
            'statuses' => [Business::STATUS_ACTIVE, Business::STATUS_PENDING_VERIFICATION, Business::STATUS_SUSPENDED, Business::STATUS_CLOSING],
        ]);
    }

    public function show(Business $business): View
    {
        $figures = BusinessDirectory::query()->whereKey($business->id)->firstOrFail();

        // Read in the business: its subscription and its members' roles there.
        [$subscription, $readOnly, $members, $payments] = $this->tenant->run($business, fn (): array => [
            Subscription::query()->with('plan')->first(),
            app(Entitlements::class)->readOnlyReason(),
            User::query()->inCurrentBusiness()->withBusinessStatus()->with('roles')->orderBy('name')->get(),
            PlatformPayment::query()->with('recorder:id,name')->latest()->limit(20)->get(),
        ]);

        PlatformAudit::record('business.viewed', $business);

        return view('platform.businesses.show', [
            'business' => $figures,
            'subscription' => $subscription,
            'readOnly' => $readOnly,
            'members' => $members,
            'payments' => $payments,
            'minTopUp' => (int) config('billing.ai_min_topup', 500),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function suspend(SuspendBusinessRequest $request, Business $business, SuspendBusiness $suspend): RedirectResponse
    {
        return $this->attempt($business, fn () => $suspend->execute($business, $request->user(), $request->validated('reason')), __('The business is suspended.'));
    }

    public function reactivate(Business $business, ReactivateBusiness $reactivate): RedirectResponse
    {
        return $this->attempt($business, fn () => $reactivate->execute($business), __('The business is reactivated.'));
    }

    public function changePlan(ChangeBusinessPlanRequest $request, Business $business, ChangeBusinessPlan $change): RedirectResponse
    {
        $plan = Plan::query()->findOrFail($request->validated('plan_id'));

        return $this->attempt($business, fn () => $change->execute($business, $plan), __('The plan is changed: :plan from the next payment.', ['plan' => $plan->name]));
    }

    public function extendTrial(ExtendTrialRequest $request, Business $business, ExtendTrial $extend): RedirectResponse
    {
        return $this->attempt($business, fn () => $extend->execute($business, (int) $request->validated('days')), __('The trial is extended.'));
    }

    /**
     * Runs a console action, turning a refusal into a message on the page.
     */
    private function attempt(Business $business, \Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return redirect()->route('platform.businesses.show', $business)->with('error', $e->getMessage());
        }

        return redirect()->route('platform.businesses.show', $business)->with('success', $success);
    }
}
