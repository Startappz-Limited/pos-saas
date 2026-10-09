<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private CategoryService $categoryService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $categories = $this->categoryService->getAllCategories(
            perPage: $request->get('per_page', 15),
            search: $request->get('search'),
            status: $request->get('status')
        );

        $statistics = $this->categoryService->getStatistics();

        return view('categories.index', compact('categories', 'statistics'));
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        $rootCategories = $this->categoryService->getRootCategories('active');

        return view('categories.create', compact('rootCategories'));
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', Category::class);

        $category = $this->categoryService->createCategory($request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category created successfully.');
    }

    public function show(Category $category): View
    {
        $this->authorize('view', $category);

        $category->load(['parent', 'children', 'creator', 'updater']);

        return view('categories.show', compact('category'));
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        $rootCategories = Category::rootCategories()
            ->where('id', '!=', $category->id)
            ->ordered()
            ->get();

        return view('categories.edit', compact('category', 'rootCategories'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->categoryService->updateCategory($category, $request->validated());

        return redirect()
            ->route('categories.show', $category)
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->deleteCategory($category);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    public function activate(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->categoryService->activateCategory($category);

        return back()->with('success', 'Category activated successfully.');
    }

    public function deactivate(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $this->categoryService->deactivateCategory($category);

        return back()->with('success', 'Category deactivated successfully.');
    }

    public function tree(Request $request): View
    {
        $this->authorize('viewAny', Category::class);

        $categoryTree = $this->categoryService->getCategoryTree($request->get('status'));

        return view('categories.tree', compact('categoryTree'));
    }
}
