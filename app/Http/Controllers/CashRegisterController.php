<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashRegisterController extends Controller
{
    /**
     * Display register dashboard
     */
    public function index(Request $request): View
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $query = CashRegister::with(['user', 'closedBy'])
            ->where('shop_id', $shopId)
            ->latest('register_date');

        if ($request->filled('date')) {
            $query->whereDate('register_date', $request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $registers = $query->paginate(20);
        $activeRegister = CashRegister::getActiveRegister($shopId);

        $statistics = [
            'today_sales' => $activeRegister?->total_sales ?? 0,
            'today_transactions' => $activeRegister?->transaction_count ?? 0,
            'cash_sales' => $activeRegister?->total_cash_sales ?? 0,
            'card_sales' => $activeRegister?->total_card_sales ?? 0,
            'expense_opening_balance' => $activeRegister?->expense_opening_balance ?? 0,
            'expense_balance_used' => $activeRegister?->expense_balance_used ?? 0,
            'remaining_expense_balance' => $activeRegister?->getRemainingExpenseBalance() ?? 0,
        ];

        return view('cash-registers.index', compact('registers', 'activeRegister', 'statistics'));
    }

    /**
     * Show register details
     */
    public function show(CashRegister $cashRegister): View
    {
        $cashRegister->load(['user', 'closedBy', 'sales.customer', 'expenses.category']);

        return view('cash-registers.show', compact('cashRegister'));
    }

    /**
     * Open a new register
     */
    public function open(): View
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if ($activeRegister) {
            return redirect()
                ->route('cash-registers.index')
                ->with('error', 'A register is already open for today.');
        }

        return view('cash-registers.open');
    }

    /**
     * Store new register opening
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'opening_balance' => 'required|numeric|min:0',
            'expense_opening_balance' => 'nullable|numeric|min:0',
            'opening_notes' => 'nullable|string',
        ]);

        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if ($activeRegister) {
            return redirect()
                ->route('cash-registers.index')
                ->with('error', 'A register is already open for today.');
        }

        $register = CashRegister::openRegister(
            $shopId,
            $validated['opening_balance'],
            $validated['opening_notes'],
            $validated['expense_opening_balance'] ?? 0
        );

        return redirect()
            ->route('cash-registers.index')
            ->with('success', 'Register opened successfully.');
    }

    /**
     * Show close register form
     */
    public function closeForm(CashRegister $cashRegister): View
    {
        if ($cashRegister->isClosed()) {
            return redirect()
                ->route('cash-registers.show', $cashRegister)
                ->with('error', 'This register is already closed.');
        }

        $cashRegister->updateSalesTotals();

        return view('cash-registers.close', compact('cashRegister'));
    }

    /**
     * Close register
     */
    public function close(Request $request, CashRegister $cashRegister): RedirectResponse
    {
        if ($cashRegister->isClosed()) {
            return redirect()
                ->route('cash-registers.show', $cashRegister)
                ->with('error', 'This register is already closed.');
        }

        $validated = $request->validate([
            'closing_balance' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string',
        ]);

        $cashRegister->closeRegister(
            $validated['closing_balance'],
            $validated['closing_notes']
        );

        return redirect()
            ->route('cash-registers.show', $cashRegister)
            ->with('success', 'Register closed successfully.');
    }
}
