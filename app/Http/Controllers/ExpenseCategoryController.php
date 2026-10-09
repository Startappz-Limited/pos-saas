<?php

namespace App\Http\Controllers;

use App\Actions\ConfigureShopExpenseCategory;
use App\Enums\CategoryStatus;
use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $query = ExpenseCategory::with(['parent', 'children', 'creator'])
            ->withCount('expenses');

        if ($request->boolean('root_only')) {
            $query->rootCategories();
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        $types = ExpenseCategoryType::cases();
        $statuses = CategoryStatus::cases();

        return view('expense-categories.index', compact('categories', 'types', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', ExpenseCategory::class);

        $parentCategories = ExpenseCategory::active()
            ->rootCategories()
            ->orderBy('name')
            ->get();
        $types = ExpenseCategoryType::cases();

        return view('expense-categories.create', compact('parentCategories', 'types'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:expense_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string'],
            'is_operational' => ['nullable', 'boolean'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'yearly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['created_by'] = auth()->id();

        // Set boolean fields if unchecked
        $validated['is_operational'] = $request->boolean('is_operational');
        $validated['is_tax_deductible'] = $request->boolean('is_tax_deductible');
        $validated['requires_approval'] = $request->boolean('requires_approval');

        // Calculate depth if parent is provided
        if (! empty($validated['parent_id'])) {
            $parent = ExpenseCategory::find($validated['parent_id']);
            $validated['depth'] = $parent->depth + 1;
            $validated['path'] = $parent->path ? $parent->path . '/' . $parent->id : (string) $parent->id;
        }

        $category = ExpenseCategory::create($validated);

        return redirect()
            ->route('expense-categories.show', $category)
            ->with('success', 'Expense category created successfully.');
    }

    public function show(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('view', $expenseCategory);

        $expenseCategory->load(['parent', 'children', 'creator', 'updater']);
        $expenseCategory->loadCount('expenses');

        // Budget usage statistics
        $monthlyUsage = $expenseCategory->getBudgetUsagePercent('monthly');
        $yearlyUsage = $expenseCategory->getBudgetUsagePercent('yearly');

        // Recent expenses
        $recentExpenses = $expenseCategory->expenses()
            ->with(['creator'])
            ->latest('expense_date')
            ->limit(10)
            ->get();

        return view('expense-categories.show', compact(
            'expenseCategory',
            'monthlyUsage',
            'yearlyUsage',
            'recentExpenses'
        ));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('update', $expenseCategory);

        $parentCategories = ExpenseCategory::active()
            ->rootCategories()
            ->where('id', '!=', $expenseCategory->id)
            ->orderBy('name')
            ->get();
        $types = ExpenseCategoryType::cases();
        $statuses = CategoryStatus::cases();

        return view('expense-categories.edit', compact(
            'expenseCategory',
            'parentCategories',
            'types',
            'statuses'
        ));
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['sometimes', 'string'],
            'is_operational' => ['nullable', 'boolean'],
            'is_tax_deductible' => ['nullable', 'boolean'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'yearly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string'],
        ]);

        $validated['updated_by'] = auth()->id();

        // Set boolean fields if unchecked
        $validated['is_operational'] = $request->boolean('is_operational');
        $validated['is_tax_deductible'] = $request->boolean('is_tax_deductible');
        $validated['requires_approval'] = $request->boolean('requires_approval');

        $expenseCategory->update($validated);

        return redirect()
            ->route('expense-categories.show', $expenseCategory)
            ->with('success', 'Expense category updated successfully.');
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        // Check if category has expenses
        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', 'Cannot delete category with existing expenses.');
        }

        // Check if category has children
        if ($expenseCategory->children()->exists()) {
            return back()->with('error', 'Cannot delete category with sub-categories.');
        }

        $expenseCategory->delete();

        return redirect()
            ->route('expense-categories.index')
            ->with('success', 'Expense category deleted successfully.');
    }

    public function children(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('view', $expenseCategory);

        $children = $expenseCategory->children()
            ->with(['children'])
            ->withCount('expenses')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('expense-categories.children', compact('expenseCategory', 'children'));
    }

    public function hierarchy(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('view', $expenseCategory);

        // Build full hierarchy from root to this category
        $hierarchy = collect([$expenseCategory]);
        $current = $expenseCategory;

        while ($current->parent_id) {
            $current = $current->parent;
            $hierarchy->prepend($current);
        }

        // Get all descendants
        $descendants = $this->getAllDescendants($expenseCategory);

        return view('expense-categories.hierarchy', compact(
            'expenseCategory',
            'hierarchy',
            'descendants'
        ));
    }

    public function configureShop(Request $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        $validated = $request->validate([
            'shop_id' => ['required', 'exists:shops,id'],
            'is_enabled' => ['nullable', 'boolean'],
            'monthly_budget' => ['nullable', 'numeric', 'min:0'],
            'yearly_budget' => ['nullable', 'numeric', 'min:0'],
            'requires_approval' => ['nullable', 'boolean'],
            'approval_threshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['requires_approval'] = $request->boolean('requires_approval');

        $action = app(ConfigureShopExpenseCategory::class);
        $action->handle(
            $expenseCategory,
            $validated['shop_id'],
            $validated
        );

        return redirect()
            ->route('expense-categories.show', $expenseCategory)
            ->with('success', 'Shop configuration updated successfully.');
    }

    public function budgetReport(Request $request): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', now()->endOfMonth()->toDateString());

        $categories = ExpenseCategory::active()
            ->withCount('expenses')
            ->get()
            ->map(function ($category) use ($startDate, $endDate) {
                $category->period_expenses = $category->getTotalExpenses($startDate, $endDate);
                $category->monthly_usage_percent = $category->getBudgetUsagePercent('monthly');
                $category->yearly_usage_percent = $category->getBudgetUsagePercent('yearly');

                return $category;
            });

        // Category-wise summary
        $summary = [
            'total_budget' => $categories->sum('monthly_budget'),
            'total_spent' => $categories->sum('period_expenses'),
            'categories_over_budget' => $categories->filter(fn($c) => ($c->monthly_usage_percent ?? 0) > 100)->count(),
            'categories_near_budget' => $categories->filter(fn($c) => ($c->monthly_usage_percent ?? 0) > 80 && ($c->monthly_usage_percent ?? 0) <= 100)->count(),
        ];

        return view('expense-categories.budget-report', compact(
            'categories',
            'summary',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Get all descendants recursively.
     */
    private function getAllDescendants(ExpenseCategory $category): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($category->children()->with('children')->get() as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getAllDescendants($child));
        }

        return $descendants;
    }
}
