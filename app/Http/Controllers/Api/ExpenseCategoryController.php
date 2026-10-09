<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::with(['parent', 'children'])
            ->active();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter root categories only
        if ($request->boolean('root_only')) {
            $query->rootCategories();
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $expenseCategory->load(['parent', 'children']);

        // Add budget usage info
        $expenseCategory->monthly_usage_percent = $expenseCategory->getBudgetUsagePercent('monthly');
        $expenseCategory->yearly_usage_percent = $expenseCategory->getBudgetUsagePercent('yearly');

        return response()->json([
            'success' => true,
            'data' => $expenseCategory,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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

        // Calculate depth if parent is provided
        if (! empty($validated['parent_id'])) {
            $parent = ExpenseCategory::find($validated['parent_id']);
            $validated['depth'] = $parent->depth + 1;
            $validated['path'] = $parent->path ? $parent->path . '/' . $parent->id : (string) $parent->id;
        }

        $category = ExpenseCategory::create($validated);
        $category->load('parent');

        return response()->json([
            'success' => true,
            'message' => 'Expense category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
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
        $expenseCategory->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Expense category updated successfully.',
            'data' => $expenseCategory,
        ]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        // Check if category has expenses
        if ($expenseCategory->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing expenses.',
            ], 422);
        }

        // Check if category has children
        if ($expenseCategory->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with sub-categories.',
            ], 422);
        }

        $expenseCategory->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expense category deleted successfully.',
        ]);
    }
}
