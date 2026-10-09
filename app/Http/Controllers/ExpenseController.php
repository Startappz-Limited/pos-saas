<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with(['category', 'vendor', 'creator', 'approver'])
            ->latest('expense_date');

        // Filter by shop for non-admin users
        if (auth()->user()->shop_id) {
            $query->where('shop_id', auth()->user()->shop_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('is_paid')) {
            $query->where('is_paid', $request->is_paid === '1');
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [$request->start_date, $request->end_date]);
        }

        $expenses = $query->paginate(20);

        $statistics = $this->getStatistics();
        $categories = ExpenseCategory::active()->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'statistics', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        $categories = ExpenseCategory::active()->orderBy('name')->get();
        $vendors = Supplier::where('status', 'active')->orderBy('name')->get();
        $activeRegister = CashRegister::getActiveRegister();

        return view('expenses.create', compact('categories', 'vendors', 'activeRegister'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:expense_date'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'is_recurring' => ['nullable', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', 'in:daily,weekly,monthly,quarterly,yearly'],
            'recurrence_end_date' => ['nullable', 'date', 'after:expense_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'settle_from_register' => ['nullable', 'boolean'],
        ]);

        $validated['shop_id'] = auth()->user()->shop_id ?? Shop::first()?->id;
        $validated['created_by'] = auth()->id();
        $validated['status'] = ExpenseStatus::DRAFT;
        $validated['is_tax_deductible'] = $request->boolean('is_tax_deductible');
        $validated['is_recurring'] = $request->boolean('is_recurring');

        if ($request->boolean('settle_from_register')) {
            $activeRegister = CashRegister::getActiveRegister($validated['shop_id']);

            if (! $activeRegister) {
                return back()->withInput()->with('error', 'No active cash register found. Please open a register first.');
            }

            $remaining = $activeRegister->getRemainingExpenseBalance();

            if ($validated['amount'] > $remaining) {
                return back()->withInput()->with('error', 'Insufficient expense balance. Available: '.number_format($remaining, 2));
            }

            $validated['settled_from_register'] = true;
            $validated['cash_register_id'] = $activeRegister->id;
            $validated['payment_method'] = 'cash';
            $validated['is_paid'] = true;
            $validated['paid_date'] = now();
            $validated['status'] = ExpenseStatus::PAID;

            $activeRegister->increment('expense_balance_used', $validated['amount']);
        }

        $expense = Expense::create($validated);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense created successfully.');
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['category', 'vendor', 'creator', 'approver', 'parentExpense', 'childExpenses', 'cashRegister']);
        $activeRegister = CashRegister::getActiveRegister();

        return view('expenses.show', compact('expense', 'activeRegister'));
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        if (! $expense->canEdit()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be edited in its current status.');
        }

        $categories = ExpenseCategory::active()->orderBy('name')->get();
        $vendors = Supplier::where('status', 'active')->orderBy('name')->get();

        return view('expenses.edit', compact('expense', 'categories', 'vendors'));
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if (! $expense->canEdit()) {
            return redirect()->route('expenses.show', $expense)
                ->with('error', 'This expense cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'category_id' => ['required', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['updated_by'] = auth()->id();
        $validated['is_tax_deductible'] = $request->boolean('is_tax_deductible');

        $expense->update($validated);

        return redirect()->route('expenses.show', $expense)
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        if (! $expense->canEdit()) {
            return back()->with('error', 'This expense cannot be deleted in its current status.');
        }

        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Expense deleted successfully.');
    }

    public function submit(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if ($expense->status !== ExpenseStatus::DRAFT) {
            return back()->with('error', 'Only draft expenses can be submitted.');
        }

        $expense->submit();

        return back()->with('success', 'Expense submitted for approval.');
    }

    public function approve(Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        if (! $expense->canApprove()) {
            return back()->with('error', 'This expense cannot be approved in its current status.');
        }

        $expense->approve(auth()->id());

        return back()->with('success', 'Expense approved successfully.');
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);

        if (! $expense->canApprove()) {
            return back()->with('error', 'This expense cannot be rejected in its current status.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        $expense->reject($validated['rejection_reason']);

        return back()->with('success', 'Expense rejected.');
    }

    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if (! $expense->canPay()) {
            return back()->with('error', 'This expense cannot be marked as paid.');
        }

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $expense->markPaid(
            $validated['payment_method'] ?? null,
            $validated['reference_number'] ?? null
        );

        return back()->with('success', 'Expense marked as paid.');
    }

    public function settleFromRegister(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if (! $expense->canPay()) {
            return back()->with('error', 'This expense cannot be settled in its current status.');
        }

        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if (! $activeRegister) {
            return back()->with('error', 'No active cash register found. Please open a register first.');
        }

        $remaining = $activeRegister->getRemainingExpenseBalance();

        if ($expense->amount > $remaining) {
            return back()->with('error', 'Insufficient expense balance. Available: '.number_format($remaining, 2));
        }

        DB::transaction(function () use ($expense, $activeRegister) {
            $expense->settled_from_register = true;
            $expense->cash_register_id = $activeRegister->id;
            $expense->payment_method = 'cash';
            $expense->is_paid = true;
            $expense->paid_date = now();
            $expense->status = ExpenseStatus::PAID;
            $expense->save();

            $activeRegister->increment('expense_balance_used', $expense->amount);
        });

        return back()->with('success', 'Expense settled from register balance.');
    }

    public function pending(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with(['category', 'vendor', 'creator'])
            ->where('status', ExpenseStatus::PENDING)
            ->latest('submitted_at');

        if (auth()->user()->shop_id) {
            $query->where('shop_id', auth()->user()->shop_id);
        }

        $expenses = $query->paginate(20);

        return view('expenses.pending', compact('expenses'));
    }

    public function recurring(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $query = Expense::with(['category', 'vendor', 'creator'])
            ->where('is_recurring', true)
            ->latest('expense_date');

        if (auth()->user()->shop_id) {
            $query->where('shop_id', auth()->user()->shop_id);
        }

        $expenses = $query->paginate(20);

        return view('expenses.recurring', compact('expenses'));
    }

    public function summary(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $shopId = auth()->user()->shop_id;

        $query = Expense::query();
        if ($shopId) {
            $query->where('shop_id', $shopId);
        }
        $query->whereBetween('expense_date', [$startDate, $endDate]);

        $totals = [
            'total' => (clone $query)->sum('amount'),
            'paid' => (clone $query)->where('is_paid', true)->sum('amount'),
            'unpaid' => (clone $query)->where('is_paid', false)->where('status', '!=', ExpenseStatus::CANCELLED)->sum('amount'),
            'pending_count' => (clone $query)->where('status', ExpenseStatus::PENDING)->count(),
        ];

        $byCategory = (clone $query)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category:id,name,color,icon')
            ->get();

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('status')
            ->get();

        $byMonth = Expense::query()
            ->when($shopId, fn ($q) => $q->where('shop_id', $shopId))
            ->whereYear('expense_date', now()->year)
            ->select(
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return view('expenses.summary', compact('totals', 'byCategory', 'byStatus', 'byMonth', 'startDate', 'endDate'));
    }

    public function byCategory(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $shopId = auth()->user()->shop_id;

        $categories = ExpenseCategory::with(['expenses' => function ($q) use ($startDate, $endDate, $shopId) {
            $q->whereBetween('expense_date', [$startDate, $endDate]);
            if ($shopId) {
                $q->where('shop_id', $shopId);
            }
        }])->active()->get()->map(function ($category) {
            $category->total = $category->expenses->sum('amount');
            $category->count = $category->expenses->count();
            $category->monthly_usage = $category->getBudgetUsagePercent('monthly');

            return $category;
        })->sortByDesc('total');

        return view('expenses.by-category', compact('categories', 'startDate', 'endDate'));
    }

    protected function getStatistics(): array
    {
        $shopId = auth()->user()->shop_id;

        $query = Expense::query();
        if ($shopId) {
            $query->where('shop_id', $shopId);
        }

        return [
            'total' => (clone $query)->count(),
            'draft' => (clone $query)->where('status', ExpenseStatus::DRAFT)->count(),
            'pending' => (clone $query)->where('status', ExpenseStatus::PENDING)->count(),
            'approved' => (clone $query)->where('status', ExpenseStatus::APPROVED)->count(),
            'paid' => (clone $query)->where('status', ExpenseStatus::PAID)->count(),
            'this_month_total' => (clone $query)->whereMonth('expense_date', now()->month)
                ->whereYear('expense_date', now()->year)
                ->sum('amount'),
        ];
    }
}
