<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\RefundDue;
use App\Support\Sales\VoidRefunds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Refunds owed after voids (stage 10, step 4): the list for owners and the
 * people who pay them, and recording one paid or (a cheque) returned. Cash
 * comes out of the payer's own open session; M-Pesa, card and bank refunds are
 * done outside the system and only recorded here.
 */
class RefundDueController extends Controller
{
    public function __construct(private VoidRefunds $refunds) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', RefundDue::class);
        $status = in_array($request->input('status'), ['pending', 'paid', 'settled'], true) ? $request->input('status') : 'pending';

        return view('refunds-due.index', [
            'refunds' => RefundDue::query()->where('status', $status)
                ->with(['sale:id,uuid,invoice_number', 'shop:id,name', 'creator:id,name', 'resolver:id,name'])
                ->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'ownSessionShops' => fn (int $shopId): bool => $this->refunds->ownSession($request->user(), $shopId) !== null,
        ]);
    }

    public function pay(Request $request, RefundDue $refundDue): RedirectResponse
    {
        $this->authorize('pay', $refundDue);

        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['paid', 'cheque_returned'])],
            'method' => ['nullable', Rule::in(RefundDue::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->refunds->pay($refundDue, $request->user(), $validated);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $validated['resolution'] === 'cheque_returned'
            ? __('Recorded: the cheque was returned to the customer.')
            : __('Refund recorded as paid.'));
    }
}
