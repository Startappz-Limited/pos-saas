<?php

namespace App\Http\Controllers;

use App\Models\CashPayout;
use App\Models\CashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Petty cash paid out of an open session by hand (stage 10, step 4): it
 * lowers the session's expected cash, needs a reason, and is audited.
 */
class CashPayoutController extends Controller
{
    public function store(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        $this->authorize('cash-payouts.create', $cashRegister);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        CashPayout::create([
            'shop_id' => $cashRegister->shop_id,
            'cash_register_id' => $cashRegister->id,
            'amount' => round((float) $validated['amount'], 2),
            'reason' => $validated['reason'],
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', __('Payout recorded. The session\'s expected cash is lower by it.'));
    }
}
