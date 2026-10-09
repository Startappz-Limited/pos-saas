<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    /**
     * Current shop for the request.
     *
     * `User::shop_id` is an accessor over the `shop_user` pivot, so it is null
     * for any user with no shop assigned. Without the fallback every sibling API
     * controller uses, that null silently poisoned this controller: listings
     * filtered on `shop_id = null` and returned nothing, and `store()` wrote a
     * null into the non-nullable `expenses.shop_id`, producing a 500.
     */
    private function resolveShopId(): ?int
    {
        return auth()->user()->shop_id ?? Shop::first()?->id;
    }

    public function index(Request $request): JsonResponse
    {
        $shopId = $this->resolveShopId();

        $query = Expense::with(['category', 'vendor', 'creator', 'approver'])
            ->forShop($shopId);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->forDateRange($request->start_date, $request->end_date);
        } elseif ($request->filled('date')) {
            $query->whereDate('expense_date', $request->date);
        }

        // Filter by payment status
        if ($request->filled('is_paid')) {
            $query->where('is_paid', filter_var($request->is_paid, FILTER_VALIDATE_BOOLEAN));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('expense_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $expenses = $query->latest('expense_date')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $expenses->items(),
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'per_page' => $expenses->perPage(),
                'total' => $expenses->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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

        $validated['shop_id'] = $this->resolveShopId();
        $validated['created_by'] = auth()->id();
        $validated['status'] = ExpenseStatus::DRAFT;

        if ($request->boolean('settle_from_register')) {
            $activeRegister = CashRegister::getActiveRegister($validated['shop_id']);

            if (! $activeRegister) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active cash register found. Please open a register first.',
                ], 422);
            }

            $remaining = $activeRegister->getRemainingExpenseBalance();

            if ($validated['amount'] > $remaining) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient expense balance. Available: '.number_format($remaining, 2),
                ], 422);
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
        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense created successfully.',
            'data' => $expense,
        ], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        $expense->load(['category', 'vendor', 'creator', 'approver', 'parentExpense', 'childExpenses']);

        return response()->json([
            'success' => true,
            'data' => $expense,
        ]);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be edited in its current status.',
            ], 422);
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:expense_categories,id'],
            'vendor_id' => ['nullable', 'exists:suppliers,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'expense_date' => ['sometimes', 'date'],
            'due_date' => ['nullable', 'date'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $validated['updated_by'] = auth()->id();
        $expense->update($validated);
        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense updated successfully.',
            'data' => $expense,
        ]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canEdit()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be deleted in its current status.',
            ], 422);
        }

        $expense->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully.',
        ]);
    }

    public function submit(Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if ($expense->status !== ExpenseStatus::DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft expenses can be submitted.',
            ], 422);
        }

        $expense->submit();
        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense submitted for approval.',
            'data' => $expense,
        ]);
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be approved in its current status.',
            ], 422);
        }

        $expense->approve(auth()->id());
        $expense->load(['category', 'vendor', 'approver']);

        return response()->json([
            'success' => true,
            'message' => 'Expense approved successfully.',
            'data' => $expense,
        ]);
    }

    public function reject(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be rejected in its current status.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $expense->reject($validated['reason']);
        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense rejected.',
            'data' => $expense,
        ]);
    }

    public function markPaid(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canPay()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be marked as paid.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ]);

        $expense->markPaid(
            $validated['payment_method'] ?? null,
            $validated['reference_number'] ?? null
        );
        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense marked as paid.',
            'data' => $expense,
        ]);
    }

    public function settleFromRegister(Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if (! $expense->canPay()) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be settled in its current status.',
            ], 422);
        }

        $shopId = $this->resolveShopId();
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if (! $activeRegister) {
            return response()->json([
                'success' => false,
                'message' => 'No active cash register found. Please open a register first.',
            ], 422);
        }

        $remaining = $activeRegister->getRemainingExpenseBalance();

        if ($expense->amount > $remaining) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient expense balance. Available: '.number_format($remaining, 2),
            ], 422);
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

        $expense->load(['category', 'vendor']);

        return response()->json([
            'success' => true,
            'message' => 'Expense settled from register balance.',
            'data' => $expense,
        ]);
    }

    public function cancel(Expense $expense): JsonResponse
    {
        $this->authorizeShopAccess($expense);

        if ($expense->status === ExpenseStatus::PAID || $expense->status === ExpenseStatus::CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'This expense cannot be cancelled.',
            ], 422);
        }

        $expense->cancel();

        return response()->json([
            'success' => true,
            'message' => 'Expense cancelled.',
            'data' => $expense,
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $shopId = $this->resolveShopId();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = Expense::forShop($shopId)
            ->forDateRange($startDate, $endDate);

        $totalExpenses = (clone $query)->sum('amount');
        $paidExpenses = (clone $query)->paid()->sum('amount');
        $unpaidExpenses = (clone $query)->unpaid()->where('status', '!=', ExpenseStatus::CANCELLED)->sum('amount');
        $pendingApproval = (clone $query)->pending()->count();

        $byCategory = (clone $query)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color,icon')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total' => $item->total,
            ]);

        $byStatus = (clone $query)
            ->selectRaw('status, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'totals' => [
                    'total_expenses' => $totalExpenses,
                    'paid' => $paidExpenses,
                    'unpaid' => $unpaidExpenses,
                    'pending_approval_count' => $pendingApproval,
                ],
                'by_category' => $byCategory,
                'by_status' => $byStatus,
            ],
        ]);
    }

    protected function authorizeShopAccess(Expense $expense): void
    {
        if ($expense->shop_id !== $this->resolveShopId()) {
            abort(403, 'Unauthorized access to this expense.');
        }
    }
}
