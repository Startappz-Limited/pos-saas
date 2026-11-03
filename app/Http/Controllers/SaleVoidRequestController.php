<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Support\Sales\VoidRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Void requests on the web (stage 10, step 2): someone without `sales.void`
 * asks from the sale's page; an approver settles it from the sale's page or
 * the list of pending requests.
 */
class SaleVoidRequestController extends Controller
{
    public function __construct(private VoidRequests $requests) {}

    /**
     * The pending requests the person may settle, in their shops.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SaleVoidRequest::class);

        $pending = SaleVoidRequest::query()->pending()
            ->with(['sale', 'shop', 'requester'])
            ->orderBy('requested_at')
            ->get()
            ->filter(fn (SaleVoidRequest $voidRequest): bool => $request->user()->can('approve', $voidRequest) || $request->user()->can('cancel', $voidRequest));

        return view('void-requests.index', ['pending' => $pending]);
    }

    public function store(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('requestVoid', $sale);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        try {
            $this->requests->request($sale, $request->user(), $validated['reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Void requested. Someone who can void sales has been asked to approve it.'));
    }

    public function approve(Request $request, SaleVoidRequest $voidRequest): RedirectResponse
    {
        $this->authorize('approve', $voidRequest);

        try {
            $this->requests->approve($voidRequest, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Void approved: the sale is voided.'));
    }

    public function reject(Request $request, SaleVoidRequest $voidRequest): RedirectResponse
    {
        $this->authorize('reject', $voidRequest);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        try {
            $this->requests->reject($voidRequest, $request->user(), $validated['rejection_reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Void request rejected.'));
    }

    public function cancel(Request $request, SaleVoidRequest $voidRequest): RedirectResponse
    {
        $this->authorize('cancel', $voidRequest);

        try {
            $this->requests->cancel($voidRequest, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Void request cancelled.'));
    }
}
